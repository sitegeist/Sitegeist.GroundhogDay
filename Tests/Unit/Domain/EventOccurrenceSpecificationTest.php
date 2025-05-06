<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Tests\Unit\Domain;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Sitegeist\GroundhogDay\Domain\EventDates;
use Sitegeist\GroundhogDay\Domain\EventOccurrenceSpecification;
use Sitegeist\GroundhogDay\Domain\ExceptionDateTimes;
use Sitegeist\GroundhogDay\Domain\Recurrence\RecurrenceDateTimes;
use Sitegeist\GroundhogDay\Domain\Recurrence\RecurrenceRule;

class EventOccurrenceSpecificationTest extends TestCase
{
    /**
     * @param array<int,EventDates> $expectedEventDates
     * @dataProvider resolvedDatesProvider
     */
    public function testResolveDates(
        EventOccurrenceSpecification $subject,
        ?\DateTimeImmutable $afterDate,
        array $expectedEventDates,
    ): void {
        Assert::assertEquals(
            $expectedEventDates,
            iterator_to_array($subject->resolveDates($afterDate))
        );
    }

    public static function resolvedDatesProvider(): iterable
    {
        yield 'only startDate, no afterDate' => [
            'subject' => EventOccurrenceSpecification::create(
                startDate: self::createDateTime('2025-04-24 14:30:00'),
            ),
            'afterDate' => null,
            'expectedEventDates' => [
                new EventDates(
                    self::createDateTime('2025-04-24 14:30:00'),
                    self::createDateTime('2025-04-24 14:30:00'),
                )
            ],
        ];

        yield 'recurrence rule, no afterDate' => [
            'subject' => EventOccurrenceSpecification::create(
                startDate: self::createDateTime('2025-04-24 14:30:00'),
                recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=2'),
            ),
            'afterDate' => null,
            'expectedEventDates' => [
                new EventDates(
                    self::createDateTime('2025-04-24 14:30:00'),
                    self::createDateTime('2025-04-24 14:30:00'),
                ),
                new EventDates(
                    self::createDateTime('2025-05-04 14:30:00'),
                    self::createDateTime('2025-05-04 14:30:00'),
                ),
            ],
        ];

        yield 'recurrence rule, recurrence dates, no afterDate' => [
            'subject' => EventOccurrenceSpecification::create(
                startDate: self::createDateTime('2025-04-24 14:30:00'),
                recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=2'),
                recurrenceDatesTimes: RecurrenceDateTimes::create(
                    self::createDateTime('2025-05-04 14:30:00'),
                    self::createDateTime('2025-05-05 15:15:00'),
                )
            ),
            'afterDate' => null,
            'expectedEventDates' => [
                new EventDates(
                    self::createDateTime('2025-04-24 14:30:00'),
                    self::createDateTime('2025-04-24 14:30:00'),
                ),
                new EventDates(
                    self::createDateTime('2025-05-04 14:30:00'),
                    self::createDateTime('2025-05-04 14:30:00'),
                ),
                new EventDates(
                    self::createDateTime('2025-05-05 15:15:00'),
                    self::createDateTime('2025-05-05 15:15:00'),
                ),
            ],
        ];

        yield 'recurrence rule, recurrence dates, exception dates, no afterDate' => [
            'subject' => EventOccurrenceSpecification::create(
                startDate: self::createDateTime('2025-04-24 14:30:00'),
                recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=4'),
                recurrenceDatesTimes: RecurrenceDateTimes::create(
                    self::createDateTime('2025-05-04 14:30:00'),
                    self::createDateTime('2025-05-05 15:15:00'),
                ),
                exceptionDateTimes: ExceptionDateTimes::create(
                    self::createDateTime('2025-05-04 14:30:00'),
                    self::createDateTime('2025-05-14 14:30:00'),
                )
            ),
            'afterDate' => null,
            'expectedEventDates' => [
                new EventDates(
                    self::createDateTime('2025-04-24 14:30:00'),
                    self::createDateTime('2025-04-24 14:30:00'),
                ),
                new EventDates(
                    self::createDateTime('2025-05-05 15:15:00'),
                    self::createDateTime('2025-05-05 15:15:00'),
                ),
                new EventDates(
                    self::createDateTime('2025-05-24 14:30:00'),
                    self::createDateTime('2025-05-24 14:30:00'),
                ),
            ],
        ];

        yield 'end date, recurrence rule, recurrence dates, exception dates, no afterDate' => [
            'subject' => EventOccurrenceSpecification::create(
                startDate: self::createDateTime('2025-04-24 14:30:00'),
                endDate: self::createDateTime('2025-04-24 15:30:00'),
                recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=4'),
                recurrenceDatesTimes: RecurrenceDateTimes::create(
                    self::createDateTime('2025-05-04 14:30:00'),
                    self::createDateTime('2025-05-05 15:15:00'),
                ),
                exceptionDateTimes: ExceptionDateTimes::create(
                    self::createDateTime('2025-05-04 14:30:00'),
                    self::createDateTime('2025-05-14 14:30:00'),
                )
            ),
            'afterDate' => null,
            'expectedEventDates' => [
                new EventDates(
                    self::createDateTime('2025-04-24 14:30:00'),
                    self::createDateTime('2025-04-24 15:30:00'),
                ),
                new EventDates(
                    self::createDateTime('2025-05-05 15:15:00'),
                    self::createDateTime('2025-05-05 16:15:00'),
                ),
                new EventDates(
                    self::createDateTime('2025-05-24 14:30:00'),
                    self::createDateTime('2025-05-24 15:30:00'),
                ),
            ],
        ];

        yield 'duration, recurrence rule, recurrence dates, exception dates, no afterDate' => [
            'subject' => EventOccurrenceSpecification::create(
                startDate: self::createDateTime('2025-04-24 14:30:00'),
                duration: new \DAteInterval('PT2H'),
                recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=4'),
                recurrenceDatesTimes: RecurrenceDateTimes::create(
                    self::createDateTime('2025-05-04 14:30:00'),
                    self::createDateTime('2025-05-05 15:15:00'),
                ),
                exceptionDateTimes: ExceptionDateTimes::create(
                    self::createDateTime('2025-05-04 14:30:00'),
                    self::createDateTime('2025-05-14 14:30:00'),
                )
            ),
            'afterDate' => null,
            'expectedEventDates' => [
                new EventDates(
                    self::createDateTime('2025-04-24 14:30:00'),
                    self::createDateTime('2025-04-24 16:30:00'),
                ),
                new EventDates(
                    self::createDateTime('2025-05-05 15:15:00'),
                    self::createDateTime('2025-05-05 17:15:00'),
                ),
                new EventDates(
                    self::createDateTime('2025-05-24 14:30:00'),
                    self::createDateTime('2025-05-24 16:30:00'),
                ),
            ],
        ];

        yield 'no end date, recurrence rule, recurrence dates, exception dates, afterDate' => [
            'subject' => EventOccurrenceSpecification::create(
                startDate: self::createDateTime('2025-04-24 14:30:00'),
                recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=4'),
                recurrenceDatesTimes: RecurrenceDateTimes::create(
                    self::createDateTime('2025-05-04 14:30:00'),
                    self::createDateTime('2025-05-05 15:15:00'),
                ),
                exceptionDateTimes: ExceptionDateTimes::create(
                    self::createDateTime('2025-05-04 14:30:00'),
                    self::createDateTime('2025-05-14 14:30:00'),
                )
            ),
            'afterDate' => self::createDateTime('2025-05-05 00:00:00'),
            'expectedEventDates' => [
                new EventDates(
                    self::createDateTime('2025-05-05 15:15:00'),
                    self::createDateTime('2025-05-05 15:15:00'),
                ),
                new EventDates(
                    self::createDateTime('2025-05-24 14:30:00'),
                    self::createDateTime('2025-05-24 14:30:00'),
                ),
            ],
        ];

        yield 'end date, recurrence rule, recurrence dates, exception dates, afterDate' => [
            'subject' => EventOccurrenceSpecification::create(
                startDate: self::createDateTime('2025-04-24 14:30:00'),
                endDate: self::createDateTime('2025-04-24 15:30:00'),
                recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=4'),
                recurrenceDatesTimes: RecurrenceDateTimes::create(
                    self::createDateTime('2025-05-04 14:30:00'),
                    self::createDateTime('2025-05-05 15:15:00'),
                ),
                exceptionDateTimes: ExceptionDateTimes::create(
                    self::createDateTime('2025-05-04 14:30:00'),
                    self::createDateTime('2025-05-14 14:30:00'),
                )
            ),
            'afterDate' => self::createDateTime('2025-05-05 00:00:00'),
            'expectedEventDates' => [
                new EventDates(
                    self::createDateTime('2025-05-05 15:15:00'),
                    self::createDateTime('2025-05-05 16:15:00'),
                ),
                new EventDates(
                    self::createDateTime('2025-05-24 14:30:00'),
                    self::createDateTime('2025-05-24 15:30:00'),
                ),
            ],
        ];

        yield 'duration, recurrence rule, recurrence dates, exception dates, afterDate' => [
            'subject' => EventOccurrenceSpecification::create(
                startDate: self::createDateTime('2025-04-24 14:30:00'),
                duration: new \DAteInterval('PT2H'),
                recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=4'),
                recurrenceDatesTimes: RecurrenceDateTimes::create(
                    self::createDateTime('2025-05-04 14:30:00'),
                    self::createDateTime('2025-05-05 15:15:00'),
                ),
                exceptionDateTimes: ExceptionDateTimes::create(
                    self::createDateTime('2025-05-04 14:30:00'),
                    self::createDateTime('2025-05-14 14:30:00'),
                )
            ),
            'afterDate' => self::createDateTime('2025-05-05 00:00:00'),
            'expectedEventDates' => [
                new EventDates(
                    self::createDateTime('2025-05-05 15:15:00'),
                    self::createDateTime('2025-05-05 17:15:00'),
                ),
                new EventDates(
                    self::createDateTime('2025-05-24 14:30:00'),
                    self::createDateTime('2025-05-24 16:30:00'),
                ),
            ],
        ];
    }

    private static function createDateTime(string $date): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date);
    }
}
