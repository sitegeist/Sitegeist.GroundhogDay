<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Tests\Unit\Domain;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Sitegeist\GroundhogDay\Domain\DateTimeSpecification;
use Sitegeist\GroundhogDay\Domain\EventDates;
use Sitegeist\GroundhogDay\Domain\EventOccurrenceSpecification;
use Sitegeist\GroundhogDay\Domain\ExceptionDateTimes;
use Sitegeist\GroundhogDay\Domain\RecurrenceDateTimes;
use Sitegeist\GroundhogDay\Domain\RecurrenceRule;

class EventOccurrenceSpecificationTest extends TestCase
{
    /**
     * @param array<string,mixed> $array
     * @dataProvider arrayProvider
     */
    public function testFromArray(array $array, EventOccurrenceSpecification $expectedSpecification): void
    {
        self::assertEquals(
            $expectedSpecification,
            EventOccurrenceSpecification::fromArray($array)
        );
    }

    /**
     * @return iterable<int,array{array: array<string,mixed>, expectedSpecification: EventOccurrenceSpecification}>
     */
    public static function arrayProvider(): iterable
    {
        yield 'minimalArray' => [
            'array' => [
                'startDate' => '20250505T000000',
            ],
            'expectedSpecification' => EventOccurrenceSpecification::create(
                startDate: new DateTimeSpecification('20250505T000000'),
            )
        ];

        yield 'minimalCompleteArray' => [
            'array' => [
                'startDate' => '20250505T000000',
                'endDate' => null,
                'recurrenceRule' => null,
                'recurrenceDateTimes' => null,
                'exceptionDateTimes' => null,
                'duration' => null,
            ],
            'expectedSpecification' => EventOccurrenceSpecification::create(
                startDate: new DateTimeSpecification('20250505T000000'),
            )
        ];

        yield 'maximalArrayWithEndDate' => [
            'array' => [
                'startDate' => '20250505T000000',
                'endDate' => '20250510T000000',
                'recurrenceRule' => 'RRULE:FREQ=MONTHLY;INTERVAL=1;COUNT=8;BYSETPOS=1;BYMONTHDAY=9;BYDAY=SA,SU',
                'recurrenceDateTimes' => 'RDATE:20250509T103110',
                'exceptionDateTimes' => 'EXDATE:20250509T103111,20250509T103112',
                'duration' => null,
            ],
            'expectedSpecification' => new EventOccurrenceSpecification(
                startDate: DateTimeSpecification::fromString('20250505T000000'),
                endDate: DateTimeSpecification::fromString('20250510T000000'),
                duration: null,
                recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=MONTHLY;INTERVAL=1;COUNT=8;BYSETPOS=1;BYMONTHDAY=9;BYDAY=SA,SU'),
                recurrenceDateTimes: RecurrenceDateTimes::create(DateTimeSpecification::create('20250509T103110')),
                exceptionDateTimes: ExceptionDateTimes::create(
                    DateTimeSpecification::create('20250509T103111'),
                    DateTimeSpecification::create('20250509T103112'),
                ),
            )
        ];

        yield 'maximalArrayWithDuration' => [
            'array' => [
                'startDate' => '20250505T000000',
                'endDate' => null,
                'recurrenceRule' => 'RRULE:FREQ=MONTHLY;INTERVAL=1;COUNT=8;BYSETPOS=1;BYMONTHDAY=9;BYDAY=SA,SU',
                'recurrenceDateTimes' => 'RDATE:20250509T103110',
                'exceptionDateTimes' => 'EXDATE:20250509T103111,20250509T103112',
                'duration' => 'PT1H'
            ],
            'expectedSpecification' => new EventOccurrenceSpecification(
                startDate: DateTimeSpecification::fromString('20250505T000000'),
                endDate: null,
                duration: new \DateInterval('PT1H'),
                recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=MONTHLY;INTERVAL=1;COUNT=8;BYSETPOS=1;BYMONTHDAY=9;BYDAY=SA,SU'),
                recurrenceDateTimes: RecurrenceDateTimes::create(DateTimeSpecification::create('20250509T103110')),
                exceptionDateTimes: ExceptionDateTimes::create(
                    DateTimeSpecification::create('20250509T103111'),
                    DateTimeSpecification::create('20250509T103112'),
                ),
            )
        ];
    }

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
            iterator_to_array($subject->resolveDates($afterDate, null, new \DateTimeZone('UTC')))
        );
    }

    public static function resolvedDatesProvider(): iterable
    {
        yield 'only startDate, no afterDate' => [
            'subject' => EventOccurrenceSpecification::create(
                startDate: DateTimeSpecification::create('20250424T143000'),
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
                startDate: DateTimeSpecification::create('20250424T143000'),
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
                startDate: DateTimeSpecification::create('20250424T143000'),
                recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=2'),
                recurrenceDatesTimes: RecurrenceDateTimes::create(
                    DateTimeSpecification::create('20250504T143000'),
                    DateTimeSpecification::create('20250505T151500'),
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
                startDate: DateTimeSpecification::create('20250424T143000'),
                recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=4'),
                recurrenceDatesTimes: RecurrenceDateTimes::create(
                    DateTimeSpecification::create('20250504T143000'),
                    DateTimeSpecification::create('20250505T151500'),
                ),
                exceptionDateTimes: ExceptionDateTimes::create(
                    DateTimeSpecification::create('20250504T143000'),
                    DateTimeSpecification::create('20250514T143000'),
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
                startDate: DateTimeSpecification::create('20250424T143000'),
                endDate: DateTimeSpecification::create('20250424T153000'),
                recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=4'),
                recurrenceDatesTimes: RecurrenceDateTimes::create(
                    DateTimeSpecification::create('20250504T143000'),
                    DateTimeSpecification::create('20250505T151500'),
                ),
                exceptionDateTimes: ExceptionDateTimes::create(
                    DateTimeSpecification::create('20250504T143000'),
                    DateTimeSpecification::create('20250514T143000'),
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
                startDate: DateTimeSpecification::create('20250424T143000'),
                duration: new \DateInterval('PT2H'),
                recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=4'),
                recurrenceDatesTimes: RecurrenceDateTimes::create(
                    DateTimeSpecification::create('20250504T143000'),
                    DateTimeSpecification::create('20250505T151500'),
                ),
                exceptionDateTimes: ExceptionDateTimes::create(
                    DateTimeSpecification::create('20250504T143000'),
                    DateTimeSpecification::create('20250514T143000'),
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
                startDate: DateTimeSpecification::create('20250424T143000'),
                recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=4'),
                recurrenceDatesTimes: RecurrenceDateTimes::create(
                    DateTimeSpecification::create('20250504T143000'),
                    DateTimeSpecification::create('20250505T151500'),
                ),
                exceptionDateTimes: ExceptionDateTimes::create(
                    DateTimeSpecification::create('20250504T143000'),
                    DateTimeSpecification::create('20250514T143000'),
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
                startDate: DateTimeSpecification::create('20250424T143000'),
                endDate: DateTimeSpecification::create('20250424T153000'),
                recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=4'),
                recurrenceDatesTimes: RecurrenceDateTimes::create(
                    DateTimeSpecification::create('20250504T143000'),
                    DateTimeSpecification::create('20250505T151500'),
                ),
                exceptionDateTimes: ExceptionDateTimes::create(
                    DateTimeSpecification::create('20250504T143000'),
                    DateTimeSpecification::create('20250514T143000'),
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
                startDate: DateTimeSpecification::create('20250424T143000'),
                duration: new \DateInterval('PT2H'),
                recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=4'),
                recurrenceDatesTimes: RecurrenceDateTimes::create(
                    DateTimeSpecification::create('20250504T143000'),
                    DateTimeSpecification::create('20250505T151500'),
                ),
                exceptionDateTimes: ExceptionDateTimes::create(
                    DateTimeSpecification::create('20250504T143000'),
                    DateTimeSpecification::create('20250514T143000'),
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
        return \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date, new \DateTimeZone('UTC'));
    }
}
