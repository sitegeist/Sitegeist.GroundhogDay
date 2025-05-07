<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain;

use Neos\Flow\Annotations as Flow;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\Constraint\BeforeConstraint;

#[Flow\Proxy(false)]
final readonly class EventOccurrenceSpecification implements \JsonSerializable, \Stringable
{
    public const DATE_FORMAT = 'Ymd\THisp';
    public const DURATION_FORMAT = 'P%dDT%hH%iM%sS';

    public function __construct(
        public \DateTimeImmutable $startDate,
        public ?\DateTimeImmutable $endDate,
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
        \DateTimeImmutable $startDate,
        ?\DateTimeImmutable $endDate = null,
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
                $startDate = \DateTimeImmutable::createFromFormat(self::DATE_FORMAT, \mb_substr($part, 8));
            } elseif (\str_starts_with($part, 'DTEND:')) {
                $startDate = \DateTimeImmutable::createFromFormat(self::DATE_FORMAT, \mb_substr($part, 6));
            } elseif (\str_starts_with($part, 'DURATION:')) {
                $startDate = new \DateInterval(\mb_substr($part, 9));
            } elseif (\str_starts_with($part, 'RRULE:')) {
                $recurrenceRule = RecurrenceRule::fromString($part);
            } elseif (\str_starts_with($part, 'RDATE;')) {
                $recurrenceRule = RecurrenceDateTimes::fromString($part);
            } elseif (\str_starts_with($part, 'EXDATE;')) {
                $recurrenceRule = ExceptionDateTimes::fromString($part);
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

    public static function fromArray(array $values): self
    {
        $values = array_filter($values);
        if (!array_key_exists('startDate', $values)) {
            throw StartDateIsMissing::butWasRequired(\json_encode($values));
        }

        $startDate = \DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $values['startDate']);
        $endDate = array_key_exists('endDate', $values)
            ? \DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $values['endDate'])
            : null;

        $duration = array_key_exists('duration', $values)
            ? new \DateInterval($values['duration'])
            : null;

        $recurrenceRule = array_key_exists('recurrenceRule', $values)
            ? RecurrenceRule::fromString($values['recurrenceRule'])
            : null;

        return self::create(
            $startDate,
            $endDate,
            $duration,
            $recurrenceRule,
        );
    }

    public function resolveEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate
            ?: (
                $this->duration
                    ? $this->startDate->add($this->duration)
                    : null
            );
    }

    public function resolveDuration(): ?\DateInterval
    {
        if ($this->duration) {
            return $this->duration;
        }
        if ($this->endDate) {
            return $this->startDate->diff($this->endDate);
        }

        return null;
    }

    /**
     * @return array<int,EventDates>
     */
    public function resolveDates(?\DateTimeImmutable $afterDate = null, ?\DateInterval $recurrenceInterval = null): array
    {
        $dates = [];
        if (!$afterDate || $this->startDate >= $afterDate) {
            $dates[$this->startDate->format(self::DATE_FORMAT)] = new EventDates(
                $this->startDate,
                $this->resolveEndDate() ?: $this->startDate,
            );
        }

        if ($this->recurrenceRule !== null) {
            $dates = array_merge($dates, $this->resolveRecurrenceDates($afterDate, $recurrenceInterval));
        }

        if ($this->recurrenceDateTimes !== null) {
            foreach ($this->recurrenceDateTimes as $recurrenceDateTime) {
                if ($afterDate && $recurrenceDateTime < $afterDate) {
                    continue;
                }
                $dates[$recurrenceDateTime->format(self::DATE_FORMAT)] = $this->completeDate($recurrenceDateTime);
            }
        }

        if ($this->exceptionDateTimes !== null) {
            foreach ($this->exceptionDateTimes as $exceptionDateTime) {
                $occurrenceId = $exceptionDateTime->format(self::DATE_FORMAT);
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
    public function resolveRecurrenceDates(?\DateTimeImmutable $afterDate, ?\DateInterval $recurrenceInterval = null): array
    {
        $dates = [];
        if ($this->recurrenceRule !== null) {
            $referenceDate = $afterDate ?: $this->startDate;
            $renderer = new ArrayTransformer();
            $rule = new Rule($this->recurrenceRule->value, $this->startDate, $this->resolveEndDate());
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

    public function completeDate(\DateTimeImmutable $startDate): EventDates
    {
        $duration = $this->resolveDuration();
        $endDate = $duration ? $startDate->add($duration) : $startDate;

        return new EventDates(
            $startDate,
            $endDate,
        );
    }

    public function equals(self $other): bool
    {
        return $this->toString() === $other->toString();
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        $values = get_object_vars($this);
        $values['startDate'] = $values['startDate']->format(self::DATE_FORMAT);
        $values['endDate'] = $values['endDate']?->format(self::DATE_FORMAT);
        $values['duration'] = $values['duration']?->format(self::DURATION_FORMAT);

        return $values;
    }

    public function toString(): string
    {
        return implode("\n", array_filter([
            'DTSTART:' . $this->startDate->format(self::DATE_FORMAT),
            $this->endDate ? 'DTEND:' . $this->endDate->format(self::DATE_FORMAT) : null,
            $this->duration ? 'DURATION:' . $this->duration->format(self::DURATION_FORMAT) : null,
            $this->recurrenceRule?->toString(),
            $this->recurrenceDateTimes?->toString(),
            $this->exceptionDateTimes?->toString(),
        ]));
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
