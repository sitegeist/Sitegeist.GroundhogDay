<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain;

use Neos\Flow\Annotations as Flow;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\Constraint\BeforeConstraint;

#[Flow\Proxy(false)]
final readonly class EventOccurrenceSpecification implements \JsonSerializable
{
    /**
     * We use local time relative to the event venue, as specified in https://icalendar.org/iCalendar-RFC-5545/3-3-5-date-time.html Form #1
     */
    public const DATE_FORMAT = 'Ymd\THisp';
    public const DURATION_FORMAT = 'P%dDT%hH%iM%sS';

    public function __construct(
        public DateTimeSpecification $startDate,
        public ?DateTimeSpecification $endDate,
        public ?\DateInterval $duration,
        public ?RecurrenceRule $recurrenceRule,
        public ?RecurrenceDateTimes $recurrenceDateTimes,
        public ?ExceptionDateTimes $exceptionDateTimes,
    ) {
        if ($endDate !== null && $duration !== null) {
            throw new \Exception('End date and duration must not be set simultaneously', 1746526258);
        }
    }

    public static function create(
        DateTimeSpecification $startDate,
        ?DateTimeSpecification $endDate = null,
        ?\DateInterval $duration = null,
        ?RecurrenceRule $recurrenceRule = null,
        ?RecurrenceDateTimes $recurrenceDatesTimes = null,
        ?ExceptionDateTimes $exceptionDateTimes = null,
    ): self {
        return new self(
            $startDate,
            $endDate,
            $duration,
            $recurrenceRule,
            $recurrenceDatesTimes,
            $exceptionDateTimes
        );
    }

    public static function fromString(string $value): self
    {
        $startDate = null;
        $endDate = null;
        $duration = null;
        $recurrenceRule = null;
        $recurrenceDatesTimes = null;
        $exceptionDateTimes = null;

        $parts = \explode("\n", $value);

        foreach ($parts as $part) {
            if (\str_starts_with($part, 'DTSTART:')) {
                $startDate = DateTimeSpecification::fromString(\mb_substr($part, 8));
            } elseif (\str_starts_with($part, 'DTEND:')) {
                $endDate = DateTimeSpecification::fromString(\mb_substr($part, 6));
            } elseif (\str_starts_with($part, 'DURATION:')) {
                $duration = new \DateInterval(\mb_substr($part, 9));
            } elseif (\str_starts_with($part, 'RRULE:')) {
                $recurrenceRule = RecurrenceRule::fromString($part);
            } elseif (\str_starts_with($part, 'RDATE:')) {
                $recurrenceDatesTimes = RecurrenceDateTimes::fromString($part);
            } elseif (\str_starts_with($part, 'EXDATE:')) {
                $exceptionDateTimes = ExceptionDateTimes::fromString($part);
            }
        }

        if ($startDate === null) {
            throw StartDateIsMissing::butWasRequired($value);
        }

        return new self(
            $startDate,
            $endDate,
            $duration,
            $recurrenceRule,
            $recurrenceDatesTimes,
            $exceptionDateTimes,
        );
    }

    /**
     * @param array<string,mixed> $values
     */
    public static function fromArray(array $values): self
    {
        $values = array_filter($values);
        if (!array_key_exists('startDate', $values)) {
            throw StartDateIsMissing::butWasRequired(\json_encode($values) ?: '[invalid JSON]');
        }

        return self::create(
            startDate: DateTimeSpecification::fromString($values['startDate']),
            endDate: array_key_exists('endDate', $values)
                ? DateTimeSpecification::fromString($values['endDate'])
                : null,
            duration: array_key_exists('duration', $values)
                ? new \DateInterval($values['duration'])
                : null,
            recurrenceRule: array_key_exists('recurrenceRule', $values)
                ? RecurrenceRule::fromString($values['recurrenceRule'])
                : null,
            recurrenceDatesTimes: array_key_exists('recurrenceDateTimes', $values)
                ? RecurrenceDateTimes::fromString($values['recurrenceDateTimes'])
                : null,
            exceptionDateTimes: array_key_exists('exceptionDateTimes', $values)
                ? ExceptionDateTimes::fromString($values['exceptionDateTimes'])
                : null,
        );
    }

    public function resolveEndDate(\DateTimeZone $locationTimezone): ?\DateTimeImmutable
    {
        return $this->endDate
            ? $this->endDate->toDateTime($locationTimezone)
            : (
                $this->duration
                    ? $this->startDate->toDateTime($locationTimezone)->add($this->duration)
                    : null
            );
    }

    public function resolveDuration(\DateTimeZone $locationTimezone): ?\DateInterval
    {
        if ($this->duration) {
            return $this->duration;
        }
        if ($this->endDate) {
            return $this->startDate->toDateTime($locationTimezone)
                ->diff($this->endDate->toDateTime($locationTimezone));
        }

        return null;
    }

    /**
     * @return array<int,EventDates>
     */
    public function resolveDates(?\DateTimeImmutable $afterDate, ?\DateInterval $recurrenceInterval, \DateTimeZone $locationTimezone): array
    {
        $dates = [];
        $startDateTime = $this->startDate->toDateTime($locationTimezone);
        if (!$afterDate || $startDateTime >= $afterDate) {
            $dates[$startDateTime->format(self::DATE_FORMAT)] = new EventDates(
                $startDateTime,
                $this->resolveEndDate($locationTimezone) ?: $startDateTime,
            );
        }

        if ($this->recurrenceRule !== null) {
            $dates = array_merge($dates, $this->resolveRecurrenceDates($afterDate, $recurrenceInterval, $locationTimezone));
        }

        if ($this->recurrenceDateTimes !== null) {
            foreach ($this->recurrenceDateTimes as $recurrenceDateTimeSpecification) {
                $recurrenceDateTime = $recurrenceDateTimeSpecification->toDateTime($locationTimezone);
                if ($afterDate && $recurrenceDateTime < $afterDate) {
                    continue;
                }
                $dates[$recurrenceDateTime->format(self::DATE_FORMAT)] = $this->completeDate($recurrenceDateTime, $locationTimezone);
            }
        }

        if ($this->exceptionDateTimes !== null) {
            foreach ($this->exceptionDateTimes as $exceptionDateTime) {
                $occurrenceId = $exceptionDateTime->toDateTime($locationTimezone)->format(self::DATE_FORMAT);
                if (array_key_exists($occurrenceId, $dates)) {
                    unset($dates[$occurrenceId]);
                }
            }
        }

        ksort($dates);

        return array_values($dates);
    }

    /**
     * @return array<string,EventDates>
     */
    public function resolveRecurrenceDates(?\DateTimeImmutable $afterDate, ?\DateInterval $recurrenceInterval, \DateTimeZone $locationTimezone): array
    {
        $dates = [];
        if ($this->recurrenceRule !== null) {
            $referenceDate = $afterDate ?: $this->startDate->toDateTime($locationTimezone);
            $renderer = new ArrayTransformer();
            $rule = new Rule($this->recurrenceRule->value, $this->startDate->toDateTime($locationTimezone), $this->resolveEndDate($locationTimezone));
            foreach (
                $renderer->transform(
                    $rule,
                    $rule->getEndDate()
                        ? null
                        : new BeforeConstraint($referenceDate->add($recurrenceInterval ?: new \DateInterval('P1Y')))
                ) as $recurrence
            ) {
                $eventDates = EventDates::tryFromRecurrence($recurrence);
                if (!$eventDates instanceof EventDates || $eventDates->startDate < $referenceDate) {
                    continue;
                }

                $dates[$eventDates->startDate->format(self::DATE_FORMAT)] = $eventDates;
            }
        }

        return $dates;
    }

    public function completeDate(\DateTimeImmutable $startDate, \DateTimeZone $locationTimezone): EventDates
    {
        $duration = $this->resolveDuration($locationTimezone);
        $endDate = $duration ? $startDate->add($duration) : $startDate;

        return new EventDates(
            $startDate,
            $endDate,
        );
    }

    public function equals(self $other): bool
    {
        $referenceTimeZone = new \DateTimeZone('UTC');
        return $this->toString($referenceTimeZone) === $other->toString($referenceTimeZone);
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        $values = get_object_vars($this);
        $values['duration'] = $values['duration']?->format(self::DURATION_FORMAT);

        return $values;
    }

    public function toString(\DateTimeZone $locationTimezone): string
    {
        return implode("\n", array_filter([
            'DTSTART;TZID=' . $locationTimezone->getName() . ':' . $this->startDate->value,
            $this->endDate ? ('DTEND;TZID=' . $locationTimezone->getName() . ':' . $this->endDate->value) : null,
            $this->duration ? 'DURATION:' . $this->duration->format(self::DURATION_FORMAT) : null,
            $this->recurrenceRule?->toString(),
            $this->recurrenceDateTimes?->toString($locationTimezone),
            $this->exceptionDateTimes?->toString($locationTimezone),
        ]));
    }
}
