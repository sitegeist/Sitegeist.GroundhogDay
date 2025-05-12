<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Tests\Functional\Domain;

use DateTimeZone;
use Neos\ContentRepository\Domain\Model\NodeData;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\Persistence\Doctrine\PersistenceManager;
use Neos\Flow\Persistence\Doctrine\Service as DoctrineService;
use Neos\Flow\Tests\FunctionalTestCase;
use PHPUnit\Framework\Assert;
use Sitegeist\GroundhogDay\Domain\DateTimeSpecification;
use Sitegeist\GroundhogDay\Domain\EventOccurrence;
use Sitegeist\GroundhogDay\Domain\EventOccurrenceRepository;
use Sitegeist\GroundhogDay\Domain\EventOccurrences;
use Sitegeist\GroundhogDay\Domain\EventOccurrenceSpecification;
use Sitegeist\GroundhogDay\Domain\ExceptionDateTimes;
use Sitegeist\GroundhogDay\Domain\RecurrenceDateTimes;
use Sitegeist\GroundhogDay\Domain\RecurrenceRule;
use Sitegeist\GroundhogDay\Domain\OccurrenceHandling\EventOccurrenceZookeeper;
use Sitegeist\GroundhogDay\Domain\OccurrenceHandling\EventOccurrenceSpecificationWasChanged;
use Sitegeist\GroundhogDay\Domain\OccurrenceHandling\EventWasCreated;
use Sitegeist\GroundhogDay\Domain\OccurrenceHandling\EventWasRemoved;
use Sitegeist\GroundhogDay\Domain\OccurrenceHandling\TimeHasPassed;

/**
 * @phpstan-type ExpectedEventAbsoluteOccurrencesWithinPeriod array{calendarId: NodeAggregateIdentifier, startDate: \DateTimeImmutable, endDate: \DateTimeImmutable, occurrences: EventOccurrences}
 * @phpstan-type ExpectedEventLocalOccurrencesWithinPeriod array{calendarId: NodeAggregateIdentifier, startDate: \DateTimeImmutable, endDate: \DateTimeImmutable, occurrences: EventOccurrences}
 * @phpstan-type ExpectedEventAbsoluteOccurrencesByEventId array{eventId: NodeAggregateIdentifier, occurrences: EventOccurrences}
 * @phpstan-type ExpectedEventLocalOccurrencesByEventId array{eventId: NodeAggregateIdentifier, occurrences: EventOccurrences}
 */
final class EventOccurrenceZookeeperTest extends FunctionalTestCase
{
    protected static $testablePersistenceEnabled = true;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpPersistence();
    }

    /**
     * @param list<ExpectedEventAbsoluteOccurrencesWithinPeriod> $expectedEventAbsoluteOccurrencesWithinPeriod
     * @param list<ExpectedEventLocalOccurrencesWithinPeriod> $expectedEventLocalOccurrencesWithinPeriod
     * @param list<ExpectedEventAbsoluteOccurrencesByEventId> $expectedEventAbsoluteOccurrencesByEventId
     * @param list<ExpectedEventLocalOccurrencesByEventId> $expectedEventLocalOccurrencesByEventId
     * @dataProvider eventCreationProvider
     */
    public function testWhenEventWasCreated(
        EventWasCreated $event,
        array $expectedEventAbsoluteOccurrencesWithinPeriod,
        array $expectedEventLocalOccurrencesWithinPeriod,
        array $expectedEventAbsoluteOccurrencesByEventId,
        array $expectedEventLocalOccurrencesByEventId,
    ): void {
        $writeSubject = $this->objectManager->get(EventOccurrenceZookeeper::class);
        $readSubject = $this->objectManager->get(EventOccurrenceRepository::class);

        $writeSubject->whenEventWasCreated($event);

        $timeZone = new DateTimeZone('UTC');

        self::assertEqualEventAbsoluteOccurrencesWithinPeriod($expectedEventAbsoluteOccurrencesWithinPeriod, $readSubject, $timeZone);
        self::assertEqualEventLocalOccurrencesWithinPeriod($expectedEventLocalOccurrencesWithinPeriod, $readSubject);
        self::assertEqualEventAbsoluteOccurrencesByEventId($expectedEventAbsoluteOccurrencesByEventId, $readSubject, $timeZone);
        self::assertEqualEventLocalOccurrencesByEventId($expectedEventLocalOccurrencesByEventId, $readSubject);
    }

    /**
     * @return iterable<string,array{
     *     event: EventWasCreated,
     *     expectedEventAbsoluteOccurrencesWithinPeriod: list<ExpectedEventAbsoluteOccurrencesWithinPeriod>,
     *     expectedEventLocalOccurrencesWithinPeriod: list<ExpectedEventLocalOccurrencesWithinPeriod>,
     *     expectedEventAbsoluteOccurrencesByEventId: list<ExpectedEventAbsoluteOccurrencesByEventId>,
     *     expectedEventLocalOccurrencesByEventId: list<ExpectedEventLocalOccurrencesByEventId>,
     * }>
     */
    public static function eventCreationProvider(): iterable
    {
        yield 'simple event' => [
            'event' => EventWasCreated::create(
                eventId: NodeAggregateIdentifier::fromString('my-event'),
                calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                occurrenceSpecification: EventOccurrenceSpecification::create(
                    DateTimeSpecification::fromString('20250424T143000'),
                ),
                locationTimezone: new \DateTimeZone('Europe/Berlin'),
            ),
            'expectedEventAbsoluteOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-21 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-27 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                        )
                    ),
                ]
            ],
            'expectedEventLocalOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => DateTimeSpecification::create('20250421T000000'),
                    'endDate' => DateTimeSpecification::create('20250427T235959'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            DateTimeSpecification::create('20250424T143000'),
                            DateTimeSpecification::create('20250424T143000'),
                        )
                    ),
                ]
            ],
            'expectedEventAbsoluteOccurrencesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                        )
                    ),
                ]
            ],
            'expectedEventLocalOccurrencesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            DateTimeSpecification::create('20250424T143000'),
                            DateTimeSpecification::create('20250424T143000'),
                        )
                    ),
                ]
            ],
        ];

        yield 'single-day event, daily, recurrence rule' => [
            'event' => EventWasCreated::create(
                eventId: NodeAggregateIdentifier::fromString('my-event'),
                calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                occurrenceSpecification: EventOccurrenceSpecification::create(
                    startDate: DateTimeSpecification::fromString('20250424T143000'),
                    recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=5'),
                ),
                locationTimezone: new \DateTimeZone('Europe/Berlin'),
            ),
            'expectedEventAbsoluteOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-23 23:59:59'),
                    'occurrences' => EventOccurrences::create()
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-24 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                        )
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-05-08 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-04 12:30:00'),
                            self::createDateTimeSpecification('2025-05-04 12:30:00'),
                        )
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-05-25 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-06-03 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-03 12:30:00'),
                            self::createDateTimeSpecification('2025-06-03 12:30:00'),
                        )
                    )
                ],
            ],
            'expectedEventLocalOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-23 23:59:59'),
                    'occurrences' => EventOccurrences::create()
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-24 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 14:30:00'),
                            self::createDateTimeSpecification('2025-04-24 14:30:00'),
                        )
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-05-08 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 14:30:00'),
                            self::createDateTimeSpecification('2025-04-24 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-04 14:30:00'),
                            self::createDateTimeSpecification('2025-05-04 14:30:00'),
                        )
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-05-25 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-06-03 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-03 14:30:00'),
                            self::createDateTimeSpecification('2025-06-03 14:30:00'),
                        )
                    )
                ],
            ],
            'expectedEventAbsoluteOccurrencesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-04 12:30:00'),
                            self::createDateTimeSpecification('2025-05-04 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-14 12:30:00'),
                            self::createDateTimeSpecification('2025-05-14 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-24 12:30:00'),
                            self::createDateTimeSpecification('2025-05-24 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-03 12:30:00'),
                            self::createDateTimeSpecification('2025-06-03 12:30:00'),
                        ),
                    )
                ]
            ],
            'expectedEventLocalOccurrencesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 14:30:00'),
                            self::createDateTimeSpecification('2025-04-24 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-04 14:30:00'),
                            self::createDateTimeSpecification('2025-05-04 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-14 14:30:00'),
                            self::createDateTimeSpecification('2025-05-14 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-24 14:30:00'),
                            self::createDateTimeSpecification('2025-05-24 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-03 14:30:00'),
                            self::createDateTimeSpecification('2025-06-03 14:30:00'),
                        ),
                    )
                ]
            ]
        ];

        yield 'single-day event, daily, recurrence rule and dates' => [
            'event' => EventWasCreated::create(
                eventId: NodeAggregateIdentifier::fromString('my-event'),
                calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                occurrenceSpecification: EventOccurrenceSpecification::create(
                    startDate: DateTimeSpecification::fromString('20250424T143000'),
                    recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=3'),
                    recurrenceDatesTimes: RecurrenceDateTimes::create(
                        DateTimeSpecification::fromString('20250504T143000'),
                        DateTimeSpecification::fromString('20250505T151500'),
                    )
                ),
                locationTimezone: new \DateTimeZone('Europe/Berlin'),
            ),
            'expectedEventAbsoluteOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-23 23:59:59'),
                    'occurrences' => EventOccurrences::create()
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-24 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                        )
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-05-08 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-04 12:30:00'),
                            self::createDateTimeSpecification('2025-05-04 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-05 13:15:00'),
                            self::createDateTimeSpecification('2025-05-05 13:15:00'),
                        ),
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-05-06 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-06-03 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-14 12:30:00'),
                            self::createDateTimeSpecification('2025-05-14 12:30:00'),
                        ),
                    )
                ],
            ],
            'expectedEventLocalOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-23 23:59:59'),
                    'occurrences' => EventOccurrences::create()
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-24 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 14:30:00'),
                            self::createDateTimeSpecification('2025-04-24 14:30:00'),
                        )
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-05-08 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 14:30:00'),
                            self::createDateTimeSpecification('2025-04-24 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-04 14:30:00'),
                            self::createDateTimeSpecification('2025-05-04 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-05 15:15:00'),
                            self::createDateTimeSpecification('2025-05-05 15:15:00'),
                        ),
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-05-06 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-06-03 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-14 14:30:00'),
                            self::createDateTimeSpecification('2025-05-14 14:30:00'),
                        ),
                    )
                ],
            ],
            'expectedEventAbsoluteDatesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-04 12:30:00'),
                            self::createDateTimeSpecification('2025-05-04 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-05 13:15:00'),
                            self::createDateTimeSpecification('2025-05-05 13:15:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-14 12:30:00'),
                            self::createDateTimeSpecification('2025-05-14 12:30:00'),
                        ),
                    )
                ]
            ],
            'expectedEventLocalDatesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 14:30:00'),
                            self::createDateTimeSpecification('2025-04-24 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-04 14:30:00'),
                            self::createDateTimeSpecification('2025-05-04 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-05 15:15:00'),
                            self::createDateTimeSpecification('2025-05-05 15:15:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-14 14:30:00'),
                            self::createDateTimeSpecification('2025-05-14 14:30:00'),
                        ),
                    )
                ]
            ],
        ];

        yield 'single-day event, daily, recurrence rule and dates and exception dates' => [
            'event' => EventWasCreated::create(
                eventId: NodeAggregateIdentifier::fromString('my-event'),
                calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                occurrenceSpecification: EventOccurrenceSpecification::create(
                    startDate: DateTimeSpecification::fromString('20250424T143000'),
                    recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=4'),
                    recurrenceDatesTimes: RecurrenceDateTimes::create(
                        DateTimeSpecification::fromString('20250504T143000'),
                        DateTimeSpecification::fromString('20250505T151500'),
                    ),
                    exceptionDateTimes: ExceptionDateTimes::create(
                        DateTimeSpecification::fromString('20250504T143000'),
                        DateTimeSpecification::fromString('20250514T143000'),
                    )
                ),
                locationTimezone: new \DateTimeZone('Europe/Berlin'),
            ),
            'expectedEventAbsoluteOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-23 23:59:59'),
                    'occurrences' => EventOccurrences::create()
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-24 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                        )
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-05-08 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-05 13:15:00'),
                            self::createDateTimeSpecification('2025-05-05 13:15:00'),
                        ),
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-05-06 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-06-03 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-24 12:30:00'),
                            self::createDateTimeSpecification('2025-05-24 12:30:00'),
                        ),
                    )
                ],
            ],
            'expectedEventLocalOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-23 23:59:59'),
                    'occurrences' => EventOccurrences::create()
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-24 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 14:30:00'),
                            self::createDateTimeSpecification('2025-04-24 14:30:00'),
                        )
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-05-08 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 14:30:00'),
                            self::createDateTimeSpecification('2025-04-24 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-05 15:15:00'),
                            self::createDateTimeSpecification('2025-05-05 15:15:00'),
                        ),
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-05-06 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-06-03 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-24 14:30:00'),
                            self::createDateTimeSpecification('2025-05-24 14:30:00'),
                        ),
                    )
                ],
            ],
            'expectedEventAbsoluteOccurrencesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-05 13:15:00'),
                            self::createDateTimeSpecification('2025-05-05 13:15:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-24 12:30:00'),
                            self::createDateTimeSpecification('2025-05-24 12:30:00'),
                        ),
                    )
                ]
            ],
            'expectedEventLocalOccurrencesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 14:30:00'),
                            self::createDateTimeSpecification('2025-04-24 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-05 15:15:00'),
                            self::createDateTimeSpecification('2025-05-05 15:15:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-24 14:30:00'),
                            self::createDateTimeSpecification('2025-05-24 14:30:00'),
                        ),
                    )
                ]
            ],
        ];

        yield 'single-day daily event, forever' => [
            'event' => EventWasCreated::create(
                eventId: NodeAggregateIdentifier::fromString('my-event'),
                calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                occurrenceSpecification: EventOccurrenceSpecification::create(
                    startDate: DateTimeSpecification::fromString('20250501T143000'),
                    recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=MONTHLY;BYMONTHDAY=1'),
                ),
                locationTimezone: new \DateTimeZone('Europe/Berlin'),
            ),
            'expectedEventAbsoluteOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-30 23:59:59'),
                    'occurrences' => EventOccurrences::create()
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-05-01 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-01 12:30:00'),
                            self::createDateTimeSpecification('2025-05-01 12:30:00'),
                        )
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-30 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-06-01 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-01 12:30:00'),
                            self::createDateTimeSpecification('2025-05-01 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-01 12:30:00'),
                            self::createDateTimeSpecification('2025-06-01 12:30:00'),
                        )
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2026-03-30 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2026-05-01 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-04-01 12:30:00'),
                            self::createDateTimeSpecification('2026-04-01 12:30:00'),
                        )
                    )
                ],
            ],
            'expectedEventLocalOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-30 23:59:59'),
                    'occurrences' => EventOccurrences::create()
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-05-01 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-01 14:30:00'),
                            self::createDateTimeSpecification('2025-05-01 14:30:00'),
                        )
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-30 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-06-01 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-01 14:30:00'),
                            self::createDateTimeSpecification('2025-05-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-01 14:30:00'),
                            self::createDateTimeSpecification('2025-06-01 14:30:00'),
                        )
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2026-03-30 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2026-05-01 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-04-01 14:30:00'),
                            self::createDateTimeSpecification('2026-04-01 14:30:00'),
                        )
                    )
                ],
            ],
            'expectedEventAbsoluteOccurrencesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-01 12:30:00'),
                            self::createDateTimeSpecification('2025-05-01 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-01 12:30:00'),
                            self::createDateTimeSpecification('2025-06-01 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-07-01 12:30:00'),
                            self::createDateTimeSpecification('2025-07-01 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-08-01 12:30:00'),
                            self::createDateTimeSpecification('2025-08-01 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-09-01 12:30:00'),
                            self::createDateTimeSpecification('2025-09-01 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-10-01 12:30:00'),
                            self::createDateTimeSpecification('2025-10-01 12:30:00'),
                        ),
                        // DST start
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-11-01 13:30:00'),
                            self::createDateTimeSpecification('2025-11-01 13:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-12-01 13:30:00'),
                            self::createDateTimeSpecification('2025-12-01 13:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-01-01 13:30:00'),
                            self::createDateTimeSpecification('2026-01-01 13:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-02-01 13:30:00'),
                            self::createDateTimeSpecification('2026-02-01 13:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-03-01 13:30:00'),
                            self::createDateTimeSpecification('2026-03-01 13:30:00'),
                        ),
                        // DST end
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-04-01 12:30:00'),
                            self::createDateTimeSpecification('2026-04-01 12:30:00'),
                        ),
                    )
                ]
            ],
            'expectedEventLocalOccurrencesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-01 14:30:00'),
                            self::createDateTimeSpecification('2025-05-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-01 14:30:00'),
                            self::createDateTimeSpecification('2025-06-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-07-01 14:30:00'),
                            self::createDateTimeSpecification('2025-07-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-08-01 14:30:00'),
                            self::createDateTimeSpecification('2025-08-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-09-01 14:30:00'),
                            self::createDateTimeSpecification('2025-09-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-10-01 14:30:00'),
                            self::createDateTimeSpecification('2025-10-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-11-01 14:30:00'),
                            self::createDateTimeSpecification('2025-11-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-12-01 14:30:00'),
                            self::createDateTimeSpecification('2025-12-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-01-01 14:30:00'),
                            self::createDateTimeSpecification('2026-01-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-02-01 14:30:00'),
                            self::createDateTimeSpecification('2026-02-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-03-01 14:30:00'),
                            self::createDateTimeSpecification('2026-03-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-04-01 14:30:00'),
                            self::createDateTimeSpecification('2026-04-01 14:30:00'),
                        ),
                    )
                ]
            ],
        ];

        yield 'multi-day event, daily, recurrence rule and dates and exception dates' => [
            'event' => EventWasCreated::create(
                eventId: NodeAggregateIdentifier::fromString('my-event'),
                calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                occurrenceSpecification: EventOccurrenceSpecification::create(
                    startDate: DateTimeSpecification::fromString('20250619T080000'),
                    endDate: DateTimeSpecification::fromString('20250620T160000'),
                    recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=1;COUNT=5'),
                    recurrenceDatesTimes: RecurrenceDateTimes::create(
                        DateTimeSpecification::fromString('20250620T080000'),
                        DateTimeSpecification::fromString('20250620T100000'),
                    ),
                    exceptionDateTimes: ExceptionDateTimes::create(
                        DateTimeSpecification::fromString('20250620T080000'),
                        DateTimeSpecification::fromString('20250621T080000'),
                    )
                ),
                locationTimezone: new \DateTimeZone('Europe/Berlin'),
            ),
            'expectedEventAbsoluteOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-06-12 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-06-18 23:59:59'),
                    'occurrences' => EventOccurrences::create()
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-06-13 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-06-19 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-19 06:00:00'),
                            self::createDateTimeSpecification('2025-06-20 14:00:00'),
                        )
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-06-14 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-06-20 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-19 06:00:00'),
                            self::createDateTimeSpecification('2025-06-20 14:00:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-20 08:00:00'),
                            self::createDateTimeSpecification('2025-06-21 16:00:00'),
                        ),
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-06-21 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-06-23 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-20 08:00:00'),
                            self::createDateTimeSpecification('2025-06-21 16:00:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-22 06:00:00'),
                            self::createDateTimeSpecification('2025-06-23 14:00:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-23 06:00:00'),
                            self::createDateTimeSpecification('2025-06-24 14:00:00'),
                        ),
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-06-24 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-06-30 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-23 06:00:00'),
                            self::createDateTimeSpecification('2025-06-24 14:00:00'),
                        ),
                    )
                ],
            ],
            'expectedEventLocalOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-06-12 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-06-18 23:59:59'),
                    'occurrences' => EventOccurrences::create()
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-06-13 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-06-19 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-19 08:00:00'),
                            self::createDateTimeSpecification('2025-06-20 16:00:00'),
                        )
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-06-14 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-06-20 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-19 08:00:00'),
                            self::createDateTimeSpecification('2025-06-20 16:00:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-20 10:00:00'),
                            self::createDateTimeSpecification('2025-06-21 18:00:00'),
                        ),
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-06-21 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-06-23 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-20 10:00:00'),
                            self::createDateTimeSpecification('2025-06-21 18:00:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-22 08:00:00'),
                            self::createDateTimeSpecification('2025-06-23 16:00:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-23 08:00:00'),
                            self::createDateTimeSpecification('2025-06-24 16:00:00'),
                        ),
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-06-24 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-06-30 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-23 08:00:00'),
                            self::createDateTimeSpecification('2025-06-24 16:00:00'),
                        ),
                    )
                ],
            ],
            'expectedEventAbsoluteOccurrencesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-19 06:00:00'),
                            self::createDateTimeSpecification('2025-06-20 14:00:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-20 08:00:00'),
                            self::createDateTimeSpecification('2025-06-21 16:00:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-22 06:00:00'),
                            self::createDateTimeSpecification('2025-06-23 14:00:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-23 06:00:00'),
                            self::createDateTimeSpecification('2025-06-24 14:00:00'),
                        ),
                    )
                ]
            ],
            'expectedEventLocalOccurrencesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-19 08:00:00'),
                            self::createDateTimeSpecification('2025-06-20 16:00:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-20 10:00:00'),
                            self::createDateTimeSpecification('2025-06-21 18:00:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-22 08:00:00'),
                            self::createDateTimeSpecification('2025-06-23 16:00:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-23 08:00:00'),
                            self::createDateTimeSpecification('2025-06-24 16:00:00'),
                        ),
                    )
                ]
            ],
        ];
    }

    /**
     * @param list<ExpectedEventAbsoluteOccurrencesWithinPeriod> $expectedEventAbsoluteOccurrencesWithinPeriod
     * @param list<ExpectedEventLocalOccurrencesWithinPeriod> $expectedEventLocalOccurrencesWithinPeriod
     * @param list<ExpectedEventAbsoluteOccurrencesByEventId> $expectedEventAbsoluteOccurrencesByEventId
     * @param list<ExpectedEventLocalOccurrencesByEventId> $expectedEventLocalOccurrencesByEventId
     * @dataProvider eventOccurrenceSpecificationChangeProvider
     */
    public function testWhenEventOccurrenceSpecificationWasChanged(
        EventWasCreated $previousStateEvent,
        EventOccurrenceSpecificationWasChanged $event,
        array $expectedEventAbsoluteOccurrencesWithinPeriod,
        array $expectedEventLocalOccurrencesWithinPeriod,
        array $expectedEventAbsoluteOccurrencesByEventId,
        array $expectedEventLocalOccurrencesByEventId,
    ): void {
        $writeSubject = $this->objectManager->get(EventOccurrenceZookeeper::class);
        $readSubject = $this->objectManager->get(EventOccurrenceRepository::class);

        $writeSubject->whenEventWasCreated($previousStateEvent);
        $writeSubject->whenEventOccurrenceSpecificationWasChanged($event);

        $timeZone = new DateTimeZone('UTC');

        self::assertEqualEventAbsoluteOccurrencesWithinPeriod($expectedEventAbsoluteOccurrencesWithinPeriod, $readSubject, $timeZone);
        self::assertEqualEventLocalOccurrencesWithinPeriod($expectedEventLocalOccurrencesWithinPeriod, $readSubject);
        self::assertEqualEventAbsoluteOccurrencesByEventId($expectedEventAbsoluteOccurrencesByEventId, $readSubject, $timeZone);
        self::assertEqualEventLocalOccurrencesByEventId($expectedEventLocalOccurrencesByEventId, $readSubject);
    }

    /**
     * @return iterable<string,array{
     *     previousStateEvent: EventWasCreated,
     *     event: EventOccurrenceSpecificationWasChanged,
     *     expectedEventAbsoluteOccurrencesWithinPeriod: list<ExpectedEventAbsoluteOccurrencesWithinPeriod>,
     *     expectedEventLocalOccurrencesWithinPeriod: list<ExpectedEventLocalOccurrencesWithinPeriod>,
     *     expectedEventAbsoluteOccurrencesByEventId: list<ExpectedEventAbsoluteOccurrencesByEventId>,
     *     expectedEventLocalOccurrencesByEventId: list<ExpectedEventLocalOccurrencesByEventId>,
     * }>
     */
    public static function eventOccurrenceSpecificationChangeProvider(): iterable
    {
        yield 'single-day daily event, changed start date' => [
            'previousStateEvent' => EventWasCreated::create(
                eventId: NodeAggregateIdentifier::fromString('my-event'),
                calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                occurrenceSpecification: EventOccurrenceSpecification::create(
                    startDate: DateTimeSpecification::fromString('20250424T143000'),
                ),
                locationTimezone: new \DateTimeZone('Europe/Berlin'),
            ),
            'event' => EventOccurrenceSpecificationWasChanged::create(
                eventId: NodeAggregateIdentifier::fromString('my-event'),
                calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                occurrenceSpecification: EventOccurrenceSpecification::create(
                    startDate: DateTimeSpecification::fromString('20250424T150000'),
                ),
                dateOfChange: self::createDateTime('2025-04-24 14:00:00', new \DateTimeZone('Europe/Berlin')),
                locationTimezone: new \DateTimeZone('Europe/Berlin'),
            ),
            'expectedEventAbsoluteOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-23 23:59:59'),
                    'occurrences' => EventOccurrences::create(),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-24 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 13:00:00'),
                            self::createDateTimeSpecification('2025-04-24 13:00:00'),
                        ),
                    ),
                ],
            ],
            'expectedEventLocalOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-23 23:59:59'),
                    'occurrences' => EventOccurrences::create(),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-24 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 15:00:00'),
                            self::createDateTimeSpecification('2025-04-24 15:00:00'),
                        ),
                    ),
                ],
            ],
            'expectedEventAbsoluteOccurrencesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 13:00:00'),
                            self::createDateTimeSpecification('2025-04-24 13:00:00'),
                        ),
                    )
                ]
            ],
            'expectedEventLocalOccurrencesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 15:00:00'),
                            self::createDateTimeSpecification('2025-04-24 15:00:00'),
                        ),
                    )
                ]
            ],
        ];

        yield 'single-day daily event, changed recurrence rule' => [
            'previousStateEvent' => EventWasCreated::create(
                eventId: NodeAggregateIdentifier::fromString('my-event'),
                calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                occurrenceSpecification: EventOccurrenceSpecification::create(
                    startDate: DateTimeSpecification::fromString('20250424T143000'),
                    recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=5'),
                ),
                locationTimezone: new \DateTimeZone('Europe/Berlin'),
            ),
            'event' => EventOccurrenceSpecificationWasChanged::create(
                eventId: NodeAggregateIdentifier::fromString('my-event'),
                calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                occurrenceSpecification: EventOccurrenceSpecification::create(
                    startDate: DateTimeSpecification::fromString('20250424T150000'),
                    recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=7;COUNT=5'),
                ),
                dateOfChange: self::createDateTime('2025-05-14 14:00:00', new \DateTimeZone('Europe/Berlin')),
                locationTimezone: new \DateTimeZone('Europe/Berlin'),
            ),
            'expectedEventAbsoluteOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-23 23:59:59'),
                    'occurrences' => EventOccurrences::create(),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-24 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                        ),
                    ),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-05-03 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-05-16 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-04 12:30:00'),
                            self::createDateTimeSpecification('2025-05-04 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-15 13:00:00'),
                            self::createDateTimeSpecification('2025-05-15 13:00:00'),
                        )
                    ),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-05-21 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-06-03 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-22 13:00:00'),
                            self::createDateTimeSpecification('2025-05-22 13:00:00'),
                        )
                    )
                ],
            ],
            'expectedEventLocalOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-23 23:59:59'),
                    'occurrences' => EventOccurrences::create(),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-24 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 14:30:00'),
                            self::createDateTimeSpecification('2025-04-24 14:30:00'),
                        ),
                    ),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-05-03 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-05-16 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-04 14:30:00'),
                            self::createDateTimeSpecification('2025-05-04 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-15 15:00:00'),
                            self::createDateTimeSpecification('2025-05-15 15:00:00'),
                        )
                    ),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-05-21 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-06-03 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-22 15:00:00'),
                            self::createDateTimeSpecification('2025-05-22 15:00:00'),
                        )
                    )
                ],
            ],
            'expectedEventAbsoluteOccurrencesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-04 12:30:00'),
                            self::createDateTimeSpecification('2025-05-04 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-15 13:00:00'),
                            self::createDateTimeSpecification('2025-05-15 13:00:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-22 13:00:00'),
                            self::createDateTimeSpecification('2025-05-22 13:00:00'),
                        ),
                    )
                ]
            ],
            'expectedEventLocalOccurrencesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 14:30:00'),
                            self::createDateTimeSpecification('2025-04-24 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-04 14:30:00'),
                            self::createDateTimeSpecification('2025-05-04 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-15 15:00:00'),
                            self::createDateTimeSpecification('2025-05-15 15:00:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-22 15:00:00'),
                            self::createDateTimeSpecification('2025-05-22 15:00:00'),
                        ),
                    )
                ]
            ],
        ];

        yield 'single-day daily event, removed recurrence rule' => [
            'previousStateEvent' => EventWasCreated::create(
                NodeAggregateIdentifier::fromString('my-event'),
                NodeAggregateIdentifier::fromString('my-calendar'),
                EventOccurrenceSpecification::create(
                    startDate: DateTimeSpecification::fromString('20250424T143000'),
                    recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=5'),
                ),
                locationTimezone: new \DateTimeZone('Europe/Berlin'),
            ),
            'event' => EventOccurrenceSpecificationWasChanged::create(
                eventId: NodeAggregateIdentifier::fromString('my-event'),
                calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                occurrenceSpecification: EventOccurrenceSpecification::create(
                    startDate: DateTimeSpecification::fromString('20250424T143000'),
                    recurrenceRule: null,
                ),
                dateOfChange: self::createDateTime('2025-05-14 14:00:00', new \DateTimeZone('Europe/Berlin')),
                locationTimezone: new \DateTimeZone('Europe/Berlin'),
            ),
            'expectedEventAbsoluteOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-23 23:59:59'),
                    'occurrences' => EventOccurrences::create(),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-24 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                        )
                    ),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-05-03 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-05-16 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-04 12:30:00'),
                            self::createDateTimeSpecification('2025-05-04 12:30:00'),
                        )
                    )
                ],
            ],
            'expectedEventLocalOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-23 23:59:59'),
                    'occurrences' => EventOccurrences::create(),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-24 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 14:30:00'),
                            self::createDateTimeSpecification('2025-04-24 14:30:00'),
                        )
                    ),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-05-03 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-05-16 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-04 14:30:00'),
                            self::createDateTimeSpecification('2025-05-04 14:30:00'),
                        )
                    )
                ],
            ],
            'expectedEventAbsoluteOccurrencesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                            self::createDateTimeSpecification('2025-04-24 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-04 12:30:00'),
                            self::createDateTimeSpecification('2025-05-04 12:30:00'),
                        ),
                    )
                ]
            ],
            'expectedEventLocalOccurrencesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-04-24 14:30:00'),
                            self::createDateTimeSpecification('2025-04-24 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-04 14:30:00'),
                            self::createDateTimeSpecification('2025-05-04 14:30:00'),
                        ),
                    )
                ]
            ],
        ];

        yield 'single-day event, changed recurrence dates' => [
              'previousStateEvent' => EventWasCreated::create(
                  eventId: NodeAggregateIdentifier::fromString('my-event'),
                  calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                  occurrenceSpecification: EventOccurrenceSpecification::create(
                      startDate: DateTimeSpecification::fromString('20250430T143000'),
                      recurrenceDatesTimes: RecurrenceDateTimes::create(
                          DateTimeSpecification::fromString('20250502T143000'),
                      )
                  ),
                  locationTimezone: new \DateTimeZone('Europe/Berlin'),
              ),
              'event' => EventOccurrenceSpecificationWasChanged::create(
                  eventId: NodeAggregateIdentifier::fromString('my-event'),
                  calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                  occurrenceSpecification: EventOccurrenceSpecification::create(
                      startDate: DateTimeSpecification::fromString('20250430T143000'),
                      recurrenceDatesTimes: RecurrenceDateTimes::create(
                          DateTimeSpecification::fromString('20250502T153000'),
                          DateTimeSpecification::fromString('20250503T143000'),
                      ),
                  ),
                  dateOfChange: self::createDateTime('2025-04-30 11:00:00', new \DateTimeZone('Europe/Berlin')),
                  locationTimezone: new \DateTimeZone('Europe/Berlin'),
              ),
              'expectedEventAbsoluteOccurrencesWithinPeriod' => [
                  [
                      'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                      'startDate' => self::createDateTimeSpecification('2025-04-30 00:00:00'),
                      'endDate' => self::createDateTimeSpecification('2025-05-01 00:00:00'),
                      'occurrences' => EventOccurrences::create(
                          EventOccurrence::create(
                              NodeAggregateIdentifier::fromString('my-event'),
                              self::createDateTimeSpecification('2025-04-30 12:30:00'),
                              self::createDateTimeSpecification('2025-04-30 12:30:00'),
                          )
                      ),
                  ],
                  [
                      'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                      'startDate' => self::createDateTimeSpecification('2025-04-30 00:00:00'),
                      'endDate' => self::createDateTimeSpecification('2025-05-03 00:00:00'),
                      'occurrences' => EventOccurrences::create(
                          EventOccurrence::create(
                              NodeAggregateIdentifier::fromString('my-event'),
                              self::createDateTimeSpecification('2025-04-30 12:30:00'),
                              self::createDateTimeSpecification('2025-04-30 12:30:00'),
                          ),
                          EventOccurrence::create(
                              NodeAggregateIdentifier::fromString('my-event'),
                              self::createDateTimeSpecification('2025-05-02 13:30:00'),
                              self::createDateTimeSpecification('2025-05-02 13:30:00'),
                          ),
                      )
                  ],
              ],
              'expectedEventLocalOccurrencesWithinPeriod' => [
                  [
                      'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                      'startDate' => self::createDateTimeSpecification('2025-04-30 00:00:00'),
                      'endDate' => self::createDateTimeSpecification('2025-05-01 00:00:00'),
                      'occurrences' => EventOccurrences::create(
                          EventOccurrence::create(
                              NodeAggregateIdentifier::fromString('my-event'),
                              self::createDateTimeSpecification('2025-04-30 14:30:00'),
                              self::createDateTimeSpecification('2025-04-30 14:30:00'),
                          )
                      ),
                  ],
                  [
                      'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                      'startDate' => self::createDateTimeSpecification('2025-04-30 00:00:00'),
                      'endDate' => self::createDateTimeSpecification('2025-05-03 00:00:00'),
                      'occurrences' => EventOccurrences::create(
                          EventOccurrence::create(
                              NodeAggregateIdentifier::fromString('my-event'),
                              self::createDateTimeSpecification('2025-04-30 14:30:00'),
                              self::createDateTimeSpecification('2025-04-30 14:30:00'),
                          ),
                          EventOccurrence::create(
                              NodeAggregateIdentifier::fromString('my-event'),
                              self::createDateTimeSpecification('2025-05-02 15:30:00'),
                              self::createDateTimeSpecification('2025-05-02 15:30:00'),
                          ),
                      )
                  ],
              ],
              'expectedEventAbsoluteOccurrencesByEventId' => [
                  [
                      'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                      'occurrences' => EventOccurrences::create(
                          EventOccurrence::create(
                              NodeAggregateIdentifier::fromString('my-event'),
                              self::createDateTimeSpecification('2025-04-30 12:30:00'),
                              self::createDateTimeSpecification('2025-04-30 12:30:00'),
                          ),
                          EventOccurrence::create(
                              NodeAggregateIdentifier::fromString('my-event'),
                              self::createDateTimeSpecification('2025-05-02 13:30:00'),
                              self::createDateTimeSpecification('2025-05-02 13:30:00'),
                          ),
                          EventOccurrence::create(
                              NodeAggregateIdentifier::fromString('my-event'),
                              self::createDateTimeSpecification('2025-05-03 12:30:00'),
                              self::createDateTimeSpecification('2025-05-03 12:30:00'),
                          ),
                      )
                  ]
              ],
              'expectedEventLocalOccurrencesByEventId' => [
                  [
                      'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                      'occurrences' => EventOccurrences::create(
                          EventOccurrence::create(
                              NodeAggregateIdentifier::fromString('my-event'),
                              self::createDateTimeSpecification('2025-04-30 14:30:00'),
                              self::createDateTimeSpecification('2025-04-30 14:30:00'),
                          ),
                          EventOccurrence::create(
                              NodeAggregateIdentifier::fromString('my-event'),
                              self::createDateTimeSpecification('2025-05-02 15:30:00'),
                              self::createDateTimeSpecification('2025-05-02 15:30:00'),
                          ),
                          EventOccurrence::create(
                              NodeAggregateIdentifier::fromString('my-event'),
                              self::createDateTimeSpecification('2025-05-03 14:30:00'),
                              self::createDateTimeSpecification('2025-05-03 14:30:00'),
                          ),
                      )
                  ]
              ],
          ];

        yield 'single-day event, removed recurrence dates' => [
              'previousStateEvent' => EventWasCreated::create(
                  eventId: NodeAggregateIdentifier::fromString('my-event'),
                  calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                  occurrenceSpecification: EventOccurrenceSpecification::create(
                      startDate: DateTimeSpecification::fromString('20250430T143000'),
                      recurrenceDatesTimes: RecurrenceDateTimes::create(
                          DateTimeSpecification::fromString('20250502T143000'),
                          DateTimeSpecification::fromString('20250504T143000'),
                      )
                  ),
                  locationTimezone: new \DateTimeZone('Europe/Berlin'),
              ),
              'event' => EventOccurrenceSpecificationWasChanged::create(
                  eventId: NodeAggregateIdentifier::fromString('my-event'),
                  calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                  occurrenceSpecification: EventOccurrenceSpecification::create(
                      startDate: DateTimeSpecification::fromString('20250430T143000'),
                      recurrenceDatesTimes: null,
                  ),
                  dateOfChange: self::createDateTime('2025-05-03 11:00:00', new \DateTimeZone('Europe/Berlin')),
                  locationTimezone: new \DateTimeZone('Europe/Berlin'),
              ),
              'expectedEventAbsoluteOccurrencesWithinPeriod' => [
                  [
                      'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                      'startDate' => self::createDateTimeSpecification('2025-04-30 00:00:00'),
                      'endDate' => self::createDateTimeSpecification('2025-05-01 00:00:00'),
                      'occurrences' => EventOccurrences::create(
                          EventOccurrence::create(
                              NodeAggregateIdentifier::fromString('my-event'),
                              self::createDateTimeSpecification('2025-04-30 12:30:00'),
                              self::createDateTimeSpecification('2025-04-30 12:30:00'),
                          )
                      )
                  ],
                  [
                      'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                      'startDate' => self::createDateTimeSpecification('2025-04-30 00:00:00'),
                      'endDate' => self::createDateTimeSpecification('2025-05-03 23:59:59'),
                      'occurrences' => EventOccurrences::create(
                          EventOccurrence::create(
                              NodeAggregateIdentifier::fromString('my-event'),
                              self::createDateTimeSpecification('2025-04-30 12:30:00'),
                              self::createDateTimeSpecification('2025-04-30 12:30:00'),
                          ),
                          EventOccurrence::create(
                              NodeAggregateIdentifier::fromString('my-event'),
                              self::createDateTimeSpecification('2025-05-02 12:30:00'),
                              self::createDateTimeSpecification('2025-05-02 12:30:00'),
                          ),
                      )
                  ],
              ],
              'expectedEventLocalOccurrencesWithinPeriod' => [
                  [
                      'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                      'startDate' => self::createDateTimeSpecification('2025-04-30 00:00:00'),
                      'endDate' => self::createDateTimeSpecification('2025-05-01 00:00:00'),
                      'occurrences' => EventOccurrences::create(
                          EventOccurrence::create(
                              NodeAggregateIdentifier::fromString('my-event'),
                              self::createDateTimeSpecification('2025-04-30 14:30:00'),
                              self::createDateTimeSpecification('2025-04-30 14:30:00'),
                          )
                      )
                  ],
                  [
                      'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                      'startDate' => self::createDateTimeSpecification('2025-04-30 00:00:00'),
                      'endDate' => self::createDateTimeSpecification('2025-05-03 23:59:59'),
                      'occurrences' => EventOccurrences::create(
                          EventOccurrence::create(
                              NodeAggregateIdentifier::fromString('my-event'),
                              self::createDateTimeSpecification('2025-04-30 14:30:00'),
                              self::createDateTimeSpecification('2025-04-30 14:30:00'),
                          ),
                          EventOccurrence::create(
                              NodeAggregateIdentifier::fromString('my-event'),
                              self::createDateTimeSpecification('2025-05-02 14:30:00'),
                              self::createDateTimeSpecification('2025-05-02 14:30:00'),
                          ),
                      )
                  ],
              ],
              'expectedEventAbsoluteOccurrencesByEventId' => [
                  [
                      'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                      'occurrences' => EventOccurrences::create(
                          EventOccurrence::create(
                              NodeAggregateIdentifier::fromString('my-event'),
                              self::createDateTimeSpecification('2025-04-30 12:30:00'),
                              self::createDateTimeSpecification('2025-04-30 12:30:00'),
                          ),
                          EventOccurrence::create(
                              NodeAggregateIdentifier::fromString('my-event'),
                              self::createDateTimeSpecification('2025-05-02 12:30:00'),
                              self::createDateTimeSpecification('2025-05-02 12:30:00'),
                          ),
                      )
                  ]
              ],
              'expectedEventLocalOccurrencesByEventId' => [
                  [
                      'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                      'occurrences' => EventOccurrences::create(
                          EventOccurrence::create(
                              NodeAggregateIdentifier::fromString('my-event'),
                              self::createDateTimeSpecification('2025-04-30 14:30:00'),
                              self::createDateTimeSpecification('2025-04-30 14:30:00'),
                          ),
                          EventOccurrence::create(
                              NodeAggregateIdentifier::fromString('my-event'),
                              self::createDateTimeSpecification('2025-05-02 14:30:00'),
                              self::createDateTimeSpecification('2025-05-02 14:30:00'),
                          ),
                      )
                  ]
              ],
          ];
    }

    /**
     * @param list<ExpectedEventAbsoluteOccurrencesWithinPeriod> $expectedEventAbsoluteOccurrencesWithinPeriod
     * @param list<ExpectedEventLocalOccurrencesWithinPeriod> $expectedEventLocalOccurrencesWithinPeriod
     * @param list<ExpectedEventAbsoluteOccurrencesByEventId> $expectedEventAbsoluteOccurrencesByEventId
     * @param list<ExpectedEventLocalOccurrencesByEventId> $expectedEventLocalOccurrencesByEventId
     * @dataProvider passageOfTimeProvider
     */
    public function testWhenTimeHasPassed(
        EventWasCreated $previousStateEvent,
        TimeHasPassed $event,
        array $expectedEventAbsoluteOccurrencesWithinPeriod,
        array $expectedEventLocalOccurrencesWithinPeriod,
        array $expectedEventAbsoluteOccurrencesByEventId,
        array $expectedEventLocalOccurrencesByEventId,
    ): void {
        $persistenceManager = $this->objectManager->get(PersistenceManager::class);
        $workspace = new Workspace('live');
        $workspaceRepository = $this->objectManager->get(WorkspaceRepository::class);
        $workspaceRepository->add($workspace);

        $nodeTypeManager = $this->objectManager->get(NodeTypeManager::class);

        $locationNodeData = new NodeData('/my-location', $workspace, 'my-location');
        $locationNodeData->setNodeType($nodeTypeManager->getNodeType('Sitegeist.GroundhogDay:Document.Location'));
        $locationNodeData->setProperty('timeZone', new \DateTimeZone('Europe/Berlin'));

        $calendarNodeData = new NodeData('/my-location/my-calendar', $workspace, 'my-calendar');
        $calendarNodeData->setNodeType($nodeTypeManager->getNodeType('Sitegeist.GroundhogDay:Document.Calendar'));

        $eventNodeData = new NodeData('/my-location/my-calendar/my-event', $workspace, (string)$previousStateEvent->eventId);
        $eventNodeData->setProperty('occurrence', $previousStateEvent->occurrenceSpecification);
        $eventNodeData->setNodeType($nodeTypeManager->getNodeType('Sitegeist.GroundhogDay:Document.Event'));

        $persistenceManager->persistAll();

        $writeSubject = $this->objectManager->get(EventOccurrenceZookeeper::class);
        $readSubject = $this->objectManager->get(EventOccurrenceRepository::class);

        $writeSubject->whenEventWasCreated($previousStateEvent);
        $writeSubject->whenTimeHasPassed($event);

        $timeZone = new DateTimeZone('UTC');

        self::assertEqualEventAbsoluteOccurrencesWithinPeriod($expectedEventAbsoluteOccurrencesWithinPeriod, $readSubject, $timeZone);
        self::assertEqualEventLocalOccurrencesWithinPeriod($expectedEventLocalOccurrencesWithinPeriod, $readSubject);
        self::assertEqualEventAbsoluteOccurrencesByEventId($expectedEventAbsoluteOccurrencesByEventId, $readSubject, $timeZone);
        self::assertEqualEventLocalOccurrencesByEventId($expectedEventLocalOccurrencesByEventId, $readSubject);
    }

    /**
     * @return iterable<string,array{
     *     previousStateEvent: EventWasCreated,
     *     event: TimeHasPassed,
     *     expectedEventAbsoluteOccurrencesWithinPeriod: list<ExpectedEventAbsoluteOccurrencesWithinPeriod>,
     *     expectedEventLocalOccurrencesWithinPeriod: list<ExpectedEventLocalOccurrencesWithinPeriod>,
     *     expectedEventAbsoluteOccurrencesByEventId: list<ExpectedEventAbsoluteOccurrencesByEventId>,
     *     expectedEventLocalOccurrencesByEventId: list<ExpectedEventLocalOccurrencesByEventId>,
     * }>
     */
    public static function passageOfTimeProvider(): iterable
    {
        yield 'single-day daily event, forever, passage of time' => [
            'previousStateEvent' => EventWasCreated::create(
                eventId: NodeAggregateIdentifier::fromString('my-event'),
                calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                occurrenceSpecification: EventOccurrenceSpecification::create(
                    startDate: DateTimeSpecification::fromString('20250501T143000'),
                    recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=MONTHLY;BYMONTHDAY=1'),
                ),
                locationTimezone: new \DateTimeZone('Europe/Berlin'),
            ),
            'event' => TimeHasPassed::create(
                self::createDateTime('2025-11-17 13:48:27'),
            ),
            'expectedEventAbsoluteOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-30 23:59:59'),
                    'occurrences' => EventOccurrences::create(),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-05-01 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-01 12:30:00'),
                            self::createDateTimeSpecification('2025-05-01 12:30:00'),
                        )
                    ),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-30 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-06-01 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-01 12:30:00'),
                            self::createDateTimeSpecification('2025-05-01 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-01 12:30:00'),
                            self::createDateTimeSpecification('2025-06-01 12:30:00'),
                        )
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-07-30 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-09-01 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-08-01 12:30:00'),
                            self::createDateTimeSpecification('2025-08-01 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-09-01 12:30:00'),
                            self::createDateTimeSpecification('2025-09-01 12:30:00'),
                        )
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2026-10-30 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2026-12-01 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-11-01 13:30:00'),
                            self::createDateTimeSpecification('2026-11-01 13:30:00'),
                        )
                    )
                ],
            ],
            'expectedEventLocalOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-30 23:59:59'),
                    'occurrences' => EventOccurrences::create(),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-05-01 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-01 14:30:00'),
                            self::createDateTimeSpecification('2025-05-01 14:30:00'),
                        )
                    ),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-30 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-06-01 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-01 14:30:00'),
                            self::createDateTimeSpecification('2025-05-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-01 14:30:00'),
                            self::createDateTimeSpecification('2025-06-01 14:30:00'),
                        )
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-07-30 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-09-01 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-08-01 14:30:00'),
                            self::createDateTimeSpecification('2025-08-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-09-01 14:30:00'),
                            self::createDateTimeSpecification('2025-09-01 14:30:00'),
                        )
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2026-10-30 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2026-12-01 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-11-01 14:30:00'),
                            self::createDateTimeSpecification('2026-11-01 14:30:00'),
                        )
                    )
                ],
            ],
            'expectedEventAbsoluteOccurrencesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-01 12:30:00'),
                            self::createDateTimeSpecification('2025-05-01 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-01 12:30:00'),
                            self::createDateTimeSpecification('2025-06-01 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-07-01 12:30:00'),
                            self::createDateTimeSpecification('2025-07-01 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-08-01 12:30:00'),
                            self::createDateTimeSpecification('2025-08-01 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-09-01 12:30:00'),
                            self::createDateTimeSpecification('2025-09-01 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-10-01 12:30:00'),
                            self::createDateTimeSpecification('2025-10-01 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-11-01 13:30:00'),
                            self::createDateTimeSpecification('2025-11-01 13:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-12-01 13:30:00'),
                            self::createDateTimeSpecification('2025-12-01 13:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-01-01 13:30:00'),
                            self::createDateTimeSpecification('2026-01-01 13:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-02-01 13:30:00'),
                            self::createDateTimeSpecification('2026-02-01 13:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-03-01 13:30:00'),
                            self::createDateTimeSpecification('2026-03-01 13:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-04-01 12:30:00'),
                            self::createDateTimeSpecification('2026-04-01 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-05-01 12:30:00'),
                            self::createDateTimeSpecification('2026-05-01 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-06-01 12:30:00'),
                            self::createDateTimeSpecification('2026-06-01 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-07-01 12:30:00'),
                            self::createDateTimeSpecification('2026-07-01 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-08-01 12:30:00'),
                            self::createDateTimeSpecification('2026-08-01 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-09-01 12:30:00'),
                            self::createDateTimeSpecification('2026-09-01 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-10-01 12:30:00'),
                            self::createDateTimeSpecification('2026-10-01 12:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-11-01 13:30:00'),
                            self::createDateTimeSpecification('2026-11-01 13:30:00'),
                        ),
                    )
                ]
            ],
            'expectedEventLocalOccurrencesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-05-01 14:30:00'),
                            self::createDateTimeSpecification('2025-05-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-06-01 14:30:00'),
                            self::createDateTimeSpecification('2025-06-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-07-01 14:30:00'),
                            self::createDateTimeSpecification('2025-07-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-08-01 14:30:00'),
                            self::createDateTimeSpecification('2025-08-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-09-01 14:30:00'),
                            self::createDateTimeSpecification('2025-09-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-10-01 14:30:00'),
                            self::createDateTimeSpecification('2025-10-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-11-01 14:30:00'),
                            self::createDateTimeSpecification('2025-11-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2025-12-01 14:30:00'),
                            self::createDateTimeSpecification('2025-12-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-01-01 14:30:00'),
                            self::createDateTimeSpecification('2026-01-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-02-01 14:30:00'),
                            self::createDateTimeSpecification('2026-02-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-03-01 14:30:00'),
                            self::createDateTimeSpecification('2026-03-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-04-01 14:30:00'),
                            self::createDateTimeSpecification('2026-04-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-05-01 14:30:00'),
                            self::createDateTimeSpecification('2026-05-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-06-01 14:30:00'),
                            self::createDateTimeSpecification('2026-06-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-07-01 14:30:00'),
                            self::createDateTimeSpecification('2026-07-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-08-01 14:30:00'),
                            self::createDateTimeSpecification('2026-08-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-09-01 14:30:00'),
                            self::createDateTimeSpecification('2026-09-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-10-01 14:30:00'),
                            self::createDateTimeSpecification('2026-10-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTimeSpecification('2026-11-01 14:30:00'),
                            self::createDateTimeSpecification('2026-11-01 14:30:00'),
                        ),
                    )
                ]
            ],
        ];
    }

    /**
     * @param list<ExpectedEventAbsoluteOccurrencesWithinPeriod> $expectedEventAbsoluteOccurrencesWithinPeriod
     * @param list<ExpectedEventLocalOccurrencesWithinPeriod> $expectedEventLocalOccurrencesWithinPeriod
     * @param list<ExpectedEventAbsoluteOccurrencesByEventId> $expectedEventAbsoluteOccurrencesByEventId
     * @param list<ExpectedEventLocalOccurrencesByEventId> $expectedEventLocalOccurrencesByEventId
     * @dataProvider eventRemovalProvider
     */
    public function testWhenNodeWasRemoved(
        EventWasCreated $previousStateEvent,
        EventWasRemoved $event,
        array $expectedEventAbsoluteOccurrencesWithinPeriod,
        array $expectedEventLocalOccurrencesWithinPeriod,
        array $expectedEventAbsoluteOccurrencesByEventId,
        array $expectedEventLocalOccurrencesByEventId,
    ) {
        $writeSubject = $this->objectManager->get(EventOccurrenceZookeeper::class);
        $readSubject = $this->objectManager->get(EventOccurrenceRepository::class);

        $writeSubject->whenEventWasCreated($previousStateEvent);
        $writeSubject->whenEventWasRemoved($event);

        $timeZone = new DateTimeZone('UTC');

        self::assertEqualEventAbsoluteOccurrencesWithinPeriod($expectedEventAbsoluteOccurrencesWithinPeriod, $readSubject, $timeZone);
        self::assertEqualEventLocalOccurrencesWithinPeriod($expectedEventLocalOccurrencesWithinPeriod, $readSubject);
        self::assertEqualEventAbsoluteOccurrencesByEventId($expectedEventAbsoluteOccurrencesByEventId, $readSubject, $timeZone);
        self::assertEqualEventLocalOccurrencesByEventId($expectedEventLocalOccurrencesByEventId, $readSubject);
    }

    /**
     * @return iterable<string,array{
     *     previousStateEvent: EventWasCreated,
     *     event: EventWasRemoved,
     *     expectedEventAbsoluteOccurrencesWithinPeriod: list<ExpectedEventAbsoluteOccurrencesWithinPeriod>,
     *     expectedEventLocalOccurrencesWithinPeriod: list<ExpectedEventLocalOccurrencesWithinPeriod>,
     *     expectedEventAbsoluteOccurrencesByEventId: list<ExpectedEventAbsoluteOccurrencesByEventId>,
     *     expectedEventLocalOccurrencesByEventId: list<ExpectedEventLocalOccurrencesByEventId>,
     * }>
     */
    public static function eventRemovalProvider(): iterable
    {
        yield 'single-day daily event, removed' => [
            'previousStateEvent' => EventWasCreated::create(
                eventId: NodeAggregateIdentifier::fromString('my-event'),
                calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                occurrenceSpecification: EventOccurrenceSpecification::create(
                    startDate: DateTimeSpecification::fromString('20250404T143000'),
                    recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=5'),
                ),
                locationTimezone: new \DateTimeZone('Europe/Berlin'),
            ),
            'event' => new EventWasRemoved(
                NodeAggregateIdentifier::fromString('my-event'),
            ),
            'expectedEventAbsoluteOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-23 23:59:59'),
                    'occurrences' => EventOccurrences::create(),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-24 23:59:59'),
                    'occurrences' => EventOccurrences::create(),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-05-03 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-05-16 23:59:59'),
                    'occurrences' => EventOccurrences::create(),
                ],
            ],
            'expectedEventLocalOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-23 23:59:59'),
                    'occurrences' => EventOccurrences::create(),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-04-24 23:59:59'),
                    'occurrences' => EventOccurrences::create(),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTimeSpecification('2025-05-03 00:00:00'),
                    'endDate' => self::createDateTimeSpecification('2025-05-16 23:59:59'),
                    'occurrences' => EventOccurrences::create(),
                ],
            ],
            'expectedEventAbsoluteOccurrencesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(),
                ]
            ],
            'expectedEventLocalOccurrencesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(),
                ]
            ],
        ];
    }

    /**
     * @param list<ExpectedEventAbsoluteOccurrencesWithinPeriod> $expectedEventOccurrencesWithinPeriod
     */
    private static function assertEqualEventAbsoluteOccurrencesWithinPeriod(array $expectedEventOccurrencesWithinPeriod, EventOccurrenceRepository $readSubject, \DateTimeZone $timeZone): void
    {
        foreach ($expectedEventOccurrencesWithinPeriod as $testRecord) {
            $expected = iterator_to_array($testRecord['occurrences']);
            $actual = iterator_to_array($readSubject->findEventAbsoluteOccurrencesWithinPeriod($testRecord['calendarId'], $testRecord['startDate'], $testRecord['endDate'], $timeZone));
            Assert::assertEquals(
                $expected,
                $actual,
                'Expected occurrences ' . PHP_EOL . \json_encode($expected) . PHP_EOL
                . ', got ' . PHP_EOL . \json_encode($actual) . PHP_EOL
                . ' for absolute period from ' . $testRecord['startDate']->value . ' to ' . $testRecord['endDate']->value,
            );
        }
    }

    /**
     * @param list<ExpectedEventLocalOccurrencesWithinPeriod> $expectedEventOccurrencesWithinPeriod
     */
    private static function assertEqualEventLocalOccurrencesWithinPeriod(array $expectedEventOccurrencesWithinPeriod, EventOccurrenceRepository $readSubject): void
    {
        foreach ($expectedEventOccurrencesWithinPeriod as $testRecord) {
            $expected = iterator_to_array($testRecord['occurrences']);
            $actual = iterator_to_array($readSubject->findEventLocalOccurrencesWithinPeriod($testRecord['calendarId'], $testRecord['startDate'], $testRecord['endDate']));
            Assert::assertEquals(
                $expected,
                $actual,
                'Expected occurrences ' . PHP_EOL . \json_encode($expected) . PHP_EOL
                . ', got ' . PHP_EOL . \json_encode($actual) . PHP_EOL
                . ' for local period from ' . $testRecord['startDate']->value . ' to ' . $testRecord['endDate']->value,
            );
        }
    }

    /**
     * @param list<ExpectedEventAbsoluteOccurrencesByEventId> $expectedEventOccurrencesByEventId
     */
    private static function assertEqualEventAbsoluteOccurrencesByEventId(array $expectedEventOccurrencesByEventId, EventOccurrenceRepository $readSubject, \DateTimeZone $timeZone): void
    {
        foreach ($expectedEventOccurrencesByEventId as $testRecord) {
            $expected = iterator_to_array($testRecord['occurrences']);
            $actual = iterator_to_array($readSubject->findEventAbsoluteOccurrencesByEventId($testRecord['eventId'], $timeZone));
            Assert::assertEquals(
                $expected,
                $actual,
                'Expected occurrences ' . PHP_EOL . \json_encode($expected) . PHP_EOL
                . ', got ' . PHP_EOL . \json_encode($actual) . PHP_EOL
                . ' for event ' . $testRecord['eventId'] . ', absolute',
            );
        }
    }

    /**
     * @param list<ExpectedEventLocalOccurrencesByEventId> $expectedEventOccurrencesByEventId
     */
    private static function assertEqualEventLocalOccurrencesByEventId(array $expectedEventOccurrencesByEventId, EventOccurrenceRepository $readSubject): void
    {
        foreach ($expectedEventOccurrencesByEventId as $testRecord) {
            $expected = iterator_to_array($testRecord['occurrences']);
            $actual = iterator_to_array($readSubject->findEventLocalOccurrencesByEventId($testRecord['eventId']));
            Assert::assertEquals(
                $expected,
                $actual,
                'Expected local occurrences ' . PHP_EOL . \json_encode($expected) . PHP_EOL
                . ', got ' . PHP_EOL . \json_encode($actual) . PHP_EOL
                . ' for event ' . $testRecord['eventId'] . ', local',
            );
        }
    }

    private static function createDateTimeSpecification(string $date, \DateTimeZone $timeZone = new \DateTimeZone('UTC')): DateTimeSpecification
    {
        return DateTimeSpecification::fromDateTimeIgnoringTimeZone(self::createDateTime($date, $timeZone));
    }

    private static function createDateTime(string $date, \DateTimeZone $timeZone = new \DateTimeZone('UTC')): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date, $timeZone);
    }

    private function setUpPersistence(): void
    {
        /** @var DoctrineService $doctrineService */
        $doctrineService = $this->objectManager->get(DoctrineService::class);
        $doctrineService->executeMigrations();
    }
}
