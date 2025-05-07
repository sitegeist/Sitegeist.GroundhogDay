<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Tests\Functional\Domain;

use Neos\ContentRepository\Domain\Model\NodeData;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\Persistence\Doctrine\PersistenceManager;
use Neos\Flow\Persistence\Doctrine\Service as DoctrineService;
use Neos\Flow\Tests\FunctionalTestCase;
use PHPUnit\Framework\Assert;
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
 * @phpstan-type ExpectedEventOccurrencesWithinPeriod array{calendarId: NodeAggregateIdentifier, startDate: \DateTimeImmutable, endDate: \DateTimeImmutable, occurrences: EventOccurrences}
 * @phpstan-type ExpectedEventOccurrencesByEventId array{eventId: NodeAggregateIdentifier, occurrences: EventOccurrences}
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
     * @param list<ExpectedEventOccurrencesWithinPeriod> $expectedEventOccurrencesWithinPeriod
     * @param list<ExpectedEventOccurrencesByEventId> $expectedEventOccurrencesByEventId
     * @dataProvider eventCreationProvider
     */
    public function testWhenEventWasCreated(
        EventWasCreated $event,
        array $expectedEventOccurrencesWithinPeriod,
        array $expectedEventOccurrencesByEventId,
    ): void {
        $writeSubject = $this->objectManager->get(EventOccurrenceZookeeper::class);
        $readSubject = $this->objectManager->get(EventOccurrenceRepository::class);

        $writeSubject->whenEventWasCreated($event);

        self::assertEqualEventOccurrencesWithinPeriod($expectedEventOccurrencesWithinPeriod, $readSubject);
        self::assertEqualEventOccurrencesByEventId($expectedEventOccurrencesByEventId, $readSubject);

    }

    /**
     * @return iterable<string,array{
     *     event: EventWasCreated,
     *     expectedEventOccurrencesWithinPeriod: list<ExpectedEventOccurrencesWithinPeriod>,
     *     expectedEventOccurrencesByEventId: list<ExpectedEventOccurrencesByEventId>,
     * }>
     */
    public static function eventCreationProvider(): iterable
    {
        yield 'simple event' => [
            'event' => EventWasCreated::create(
                eventId: NodeAggregateIdentifier::fromString('my-event'),
                calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                occurrenceSpecification: EventOccurrenceSpecification::create(
                    self::createDateTime('2025-04-24 14:30:00'),
                )
            ),
            'expectedEventOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-21 00:00:00'),
                    'endDate' => self::createDateTime('2025-04-27 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-24 14:30:00'),
                            self::createDateTime('2025-04-24 14:30:00'),
                        )
                    ),
                ]
            ],
            'expectedEventOccurrencesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-24 14:30:00'),
                            self::createDateTime('2025-04-24 14:30:00'),
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
                    startDate: self::createDateTime('2025-04-24 14:30:00'),
                    recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=5'),
                )
            ),
            'expectedEventOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTime('2025-04-23 23:59:59'),
                    'occurrences' => EventOccurrences::create()
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTime('2025-04-24 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-24 14:30:00'),
                            self::createDateTime('2025-04-24 14:30:00'),
                        )
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTime('2025-05-08 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-24 14:30:00'),
                            self::createDateTime('2025-04-24 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-04 14:30:00'),
                            self::createDateTime('2025-05-04 14:30:00'),
                        )
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-05-25 00:00:00'),
                    'endDate' => self::createDateTime('2025-06-03 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-06-03 14:30:00'),
                            self::createDateTime('2025-06-03 14:30:00'),
                        )
                    )
                ],
            ],
            'expectedEventDatesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-24 14:30:00'),
                            self::createDateTime('2025-04-24 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-04 14:30:00'),
                            self::createDateTime('2025-05-04 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-14 14:30:00'),
                            self::createDateTime('2025-05-14 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-24 14:30:00'),
                            self::createDateTime('2025-05-24 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-06-03 14:30:00'),
                            self::createDateTime('2025-06-03 14:30:00'),
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
                    startDate: self::createDateTime('2025-04-24 14:30:00'),
                    recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=3'),
                    recurrenceDatesTimes: RecurrenceDateTimes::create(
                        self::createDateTime('2025-05-04 14:30:00'),
                        self::createDateTime('2025-05-05 15:15:00'),
                    )
                )
            ),
            'expectedEventOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTime('2025-04-23 23:59:59'),
                    'occurrences' => EventOccurrences::create()
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTime('2025-04-24 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-24 14:30:00'),
                            self::createDateTime('2025-04-24 14:30:00'),
                        )
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTime('2025-05-08 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-24 14:30:00'),
                            self::createDateTime('2025-04-24 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-04 14:30:00'),
                            self::createDateTime('2025-05-04 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-05 15:15:00'),
                            self::createDateTime('2025-05-05 15:15:00'),
                        ),
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-05-06 00:00:00'),
                    'endDate' => self::createDateTime('2025-06-03 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-14 14:30:00'),
                            self::createDateTime('2025-05-14 14:30:00'),
                        ),
                    )
                ],
            ],
            'expectedEventDatesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-24 14:30:00'),
                            self::createDateTime('2025-04-24 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-04 14:30:00'),
                            self::createDateTime('2025-05-04 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-05 15:15:00'),
                            self::createDateTime('2025-05-05 15:15:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-14 14:30:00'),
                            self::createDateTime('2025-05-14 14:30:00'),
                        ),
                    )
                ]
            ]
        ];

        yield 'single-day event, daily, recurrence rule and dates and exception dates' => [
            'event' => EventWasCreated::create(
                eventId: NodeAggregateIdentifier::fromString('my-event'),
                calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                occurrenceSpecification: EventOccurrenceSpecification::create(
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
                )
            ),
            'expectedEventOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTime('2025-04-23 23:59:59'),
                    'occurrences' => EventOccurrences::create()
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTime('2025-04-24 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-24 14:30:00'),
                            self::createDateTime('2025-04-24 14:30:00'),
                        )
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTime('2025-05-08 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-24 14:30:00'),
                            self::createDateTime('2025-04-24 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-05 15:15:00'),
                            self::createDateTime('2025-05-05 15:15:00'),
                        ),
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-05-06 00:00:00'),
                    'endDate' => self::createDateTime('2025-06-03 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-24 14:30:00'),
                            self::createDateTime('2025-05-24 14:30:00'),
                        ),
                    )
                ],
            ],
            'expectedEventDatesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-24 14:30:00'),
                            self::createDateTime('2025-04-24 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-05 15:15:00'),
                            self::createDateTime('2025-05-05 15:15:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-24 14:30:00'),
                            self::createDateTime('2025-05-24 14:30:00'),
                        ),
                    )
                ]
            ]
        ];

        yield 'single-day daily event, forever' => [
            'event' => EventWasCreated::create(
                eventId: NodeAggregateIdentifier::fromString('my-event'),
                calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                occurrenceSpecification: EventOccurrenceSpecification::create(
                    startDate: self::createDateTime('2025-05-01 14:30:00'),
                    recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=MONTHLY;BYMONTHDAY=1'),
                ),
            ),
            'expectedEventOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTime('2025-04-30 23:59:59'),
                    'occurrences' => EventOccurrences::create()
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTime('2025-05-01 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-01 14:30:00'),
                            self::createDateTime('2025-05-01 14:30:00'),
                        )
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-30 00:00:00'),
                    'endDate' => self::createDateTime('2025-06-01 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-01 14:30:00'),
                            self::createDateTime('2025-05-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-06-01 14:30:00'),
                            self::createDateTime('2025-06-01 14:30:00'),
                        )
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2026-03-30 00:00:00'),
                    'endDate' => self::createDateTime('2026-05-01 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2026-04-01 14:30:00'),
                            self::createDateTime('2026-04-01 14:30:00'),
                        )
                    )
                ],
            ],
            'expectedEventDatesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-01 14:30:00'),
                            self::createDateTime('2025-05-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-06-01 14:30:00'),
                            self::createDateTime('2025-06-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-07-01 14:30:00'),
                            self::createDateTime('2025-07-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-08-01 14:30:00'),
                            self::createDateTime('2025-08-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-09-01 14:30:00'),
                            self::createDateTime('2025-09-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-10-01 14:30:00'),
                            self::createDateTime('2025-10-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-11-01 14:30:00'),
                            self::createDateTime('2025-11-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-12-01 14:30:00'),
                            self::createDateTime('2025-12-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2026-01-01 14:30:00'),
                            self::createDateTime('2026-01-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2026-02-01 14:30:00'),
                            self::createDateTime('2026-02-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2026-03-01 14:30:00'),
                            self::createDateTime('2026-03-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2026-04-01 14:30:00'),
                            self::createDateTime('2026-04-01 14:30:00'),
                        ),
                    )
                ]
            ]
        ];

        yield 'multi-day event, daily, recurrence rule and dates and exception dates' => [
            'event' => EventWasCreated::create(
                eventId: NodeAggregateIdentifier::fromString('my-event'),
                calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                occurrenceSpecification: EventOccurrenceSpecification::create(
                    startDate: self::createDateTime('2025-06-19 08:00:00'),
                    endDate: self::createDateTime('2025-06-20 16:00:00'),
                    recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=1;COUNT=5'),
                    recurrenceDatesTimes: RecurrenceDateTimes::create(
                        self::createDateTime('2025-06-20 08:00:00'),
                        self::createDateTime('2025-06-20 10:00:00'),
                    ),
                    exceptionDateTimes: ExceptionDateTimes::create(
                        self::createDateTime('2025-06-20 08:00:00'),
                        self::createDateTime('2025-06-21 08:00:00'),
                    )
                )
            ),
            'expectedEventOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-06-12 00:00:00'),
                    'endDate' => self::createDateTime('2025-06-18 23:59:59'),
                    'occurrences' => EventOccurrences::create()
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-06-13 00:00:00'),
                    'endDate' => self::createDateTime('2025-06-19 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-06-19 08:00:00'),
                            self::createDateTime('2025-06-20 16:00:00'),
                        )
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-06-14 00:00:00'),
                    'endDate' => self::createDateTime('2025-06-20 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-06-19 08:00:00'),
                            self::createDateTime('2025-06-20 16:00:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-06-20 10:00:00'),
                            self::createDateTime('2025-06-21 18:00:00'),
                        ),
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-06-21 00:00:00'),
                    'endDate' => self::createDateTime('2025-06-23 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-06-20 10:00:00'),
                            self::createDateTime('2025-06-21 18:00:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-06-22 08:00:00'),
                            self::createDateTime('2025-06-23 16:00:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-06-23 08:00:00'),
                            self::createDateTime('2025-06-24 16:00:00'),
                        ),
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-06-24 00:00:00'),
                    'endDate' => self::createDateTime('2025-06-30 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-06-23 08:00:00'),
                            self::createDateTime('2025-06-24 16:00:00'),
                        ),
                    )
                ],
            ],
            'expectedEventDatesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-06-19 08:00:00'),
                            self::createDateTime('2025-06-20 16:00:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-06-20 10:00:00'),
                            self::createDateTime('2025-06-21 18:00:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-06-22 08:00:00'),
                            self::createDateTime('2025-06-23 16:00:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-06-23 08:00:00'),
                            self::createDateTime('2025-06-24 16:00:00'),
                        ),
                    )
                ]
            ]
        ];
    }

    /**
     * @param list<ExpectedEventOccurrencesWithinPeriod> $expectedEventOccurrencesWithinPeriod
     * @param list<ExpectedEventOccurrencesByEventId> $expectedEventOccurrencesByEventId
     * @dataProvider eventOccurrenceSpecificationChangeProvider
     */
    public function testWhenEventOccurrenceSpecificationWasChanged(
        EventWasCreated $previousStateEvent,
        EventOccurrenceSpecificationWasChanged $event,
        array $expectedEventOccurrencesWithinPeriod,
        array $expectedEventOccurrencesByEventId,
    ): void {
        $writeSubject = $this->objectManager->get(EventOccurrenceZookeeper::class);
        $readSubject = $this->objectManager->get(EventOccurrenceRepository::class);

        $writeSubject->whenEventWasCreated($previousStateEvent);
        $writeSubject->whenEventOccurrenceSpecificationWasChanged($event);

        self::assertEqualEventOccurrencesWithinPeriod($expectedEventOccurrencesWithinPeriod, $readSubject);
        self::assertEqualEventOccurrencesByEventId($expectedEventOccurrencesByEventId, $readSubject);
    }

    /**
     * @return iterable<string,array{
     *     previousStateEvent: EventWasCreated,
     *     event: EventOccurrenceSpecificationWasChanged,
     *     expectedEventOccurrencesWithinPeriod: list<ExpectedEventOccurrencesWithinPeriod>,
     *     expectedEventOccurrencesByEventId: list<ExpectedEventOccurrencesByEventId>,
     * }>
     */
    public static function eventOccurrenceSpecificationChangeProvider(): iterable
    {
        yield 'single-day daily event, changed recurrence rule' => [
            'previousStateEvent' => EventWasCreated::create(
                eventId: NodeAggregateIdentifier::fromString('my-event'),
                calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                occurrenceSpecification: EventOccurrenceSpecification::create(
                    startDate: self::createDateTime('2025-04-24 14:30:00'),
                    recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=5'),
                )
            ),
            'event' => new EventOccurrenceSpecificationWasChanged(
                eventId: NodeAggregateIdentifier::fromString('my-event'),
                calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                occurrenceSpecification: EventOccurrenceSpecification::create(
                    startDate: self::createDateTime('2025-04-24 15:00:00'),
                    recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=7;COUNT=5'),
                ),
                dateOfChange: self::createDateTime('2025-05-14 14:00:00'),
            ),
            'expectedEventOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTime('2025-04-23 23:59:59'),
                    'occurrences' => EventOccurrences::create(),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTime('2025-04-24 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-24 14:30:00'),
                            self::createDateTime('2025-04-24 14:30:00'),
                        ),
                    ),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-05-03 00:00:00'),
                    'endDate' => self::createDateTime('2025-05-16 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-04 14:30:00'),
                            self::createDateTime('2025-05-04 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-15 15:00:00'),
                            self::createDateTime('2025-05-15 15:00:00'),
                        )
                    ),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-05-21 00:00:00'),
                    'endDate' => self::createDateTime('2025-06-03 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-22 15:00:00'),
                            self::createDateTime('2025-05-22 15:00:00'),
                        )
                    )
                ],
            ],
            'expectedEventDatesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-24 14:30:00'),
                            self::createDateTime('2025-04-24 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-04 14:30:00'),
                            self::createDateTime('2025-05-04 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-15 15:00:00'),
                            self::createDateTime('2025-05-15 15:00:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-22 15:00:00'),
                            self::createDateTime('2025-05-22 15:00:00'),
                        ),
                    )
                ]
            ]
        ];

        yield 'single-day daily event, removed recurrence rule' => [
            'previousStateEvent' => EventWasCreated::create(
                NodeAggregateIdentifier::fromString('my-event'),
                NodeAggregateIdentifier::fromString('my-calendar'),
                EventOccurrenceSpecification::create(
                    startDate: self::createDateTime('2025-04-24 14:30:00'),
                    recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=5'),
                )
            ),
            'event' => new EventOccurrenceSpecificationWasChanged(
                eventId: NodeAggregateIdentifier::fromString('my-event'),
                calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                occurrenceSpecification: EventOccurrenceSpecification::create(
                    startDate: self::createDateTime('2025-04-24 14:30:00'),
                    recurrenceRule: null,
                ),
                dateOfChange: self::createDateTime('2025-05-14 14:00:00'),
            ),
            'expectedEventDatesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTime('2025-04-23 23:59:59'),
                    'occurrences' => EventOccurrences::create(),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTime('2025-04-24 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-24 14:30:00'),
                            self::createDateTime('2025-04-24 14:30:00'),
                        )
                    ),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-05-03 00:00:00'),
                    'endDate' => self::createDateTime('2025-05-16 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-04 14:30:00'),
                            self::createDateTime('2025-05-04 14:30:00'),
                        )
                    )
                ],
            ],
            'expectedEventDatesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-24 14:30:00'),
                            self::createDateTime('2025-04-24 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-04 14:30:00'),
                            self::createDateTime('2025-05-04 14:30:00'),
                        ),
                    )
                ]
            ]
        ];

        yield 'single-day event, changed recurrence dates' => [
            'previousStateEvent' => EventWasCreated::create(
                eventId: NodeAggregateIdentifier::fromString('my-event'),
                calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                occurrenceSpecification: EventOccurrenceSpecification::create(
                    startDate: self::createDateTime('2025-04-30 14:30:00'),
                    recurrenceDatesTimes: RecurrenceDateTimes::create(
                        self::createDateTime('2025-05-02 14:30:00'),
                    )
                ),
            ),
            'event' => EventOccurrenceSpecificationWasChanged::create(
                eventId: NodeAggregateIdentifier::fromString('my-event'),
                calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                occurrenceSpecification: EventOccurrenceSpecification::create(
                    startDate: self::createDateTime('2025-04-30 14:30:00'),
                    recurrenceDatesTimes: RecurrenceDateTimes::create(
                        self::createDateTime('2025-05-02 15:30:00'),
                        self::createDateTime('2025-05-03 14:30:00'),
                    ),
                ),
                dateOfChange: self::createDateTime('2025-04-30 11:00:00'),
            ),
            'expectedEventOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-30 00:00:00'),
                    'endDate' => self::createDateTime('2025-05-01 00:00:00'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-30 14:30:00'),
                            self::createDateTime('2025-04-30 14:30:00'),
                        )
                    ),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-30 00:00:00'),
                    'endDate' => self::createDateTime('2025-05-03 00:00:00'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-30 14:30:00'),
                            self::createDateTime('2025-04-30 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-02 15:30:00'),
                            self::createDateTime('2025-05-02 15:30:00'),
                        ),
                    )
                ],
            ],
            'expectedEventDatesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-30 14:30:00'),
                            self::createDateTime('2025-04-30 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-02 15:30:00'),
                            self::createDateTime('2025-05-02 15:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-03 14:30:00'),
                            self::createDateTime('2025-05-03 14:30:00'),
                        ),
                    )
                ]
            ]
        ];

        yield 'single-day event, removed recurrence dates' => [
            'previousStateEvent' => EventWasCreated::create(
                eventId: NodeAggregateIdentifier::fromString('my-event'),
                calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                occurrenceSpecification: EventOccurrenceSpecification::create(
                    startDate: self::createDateTime('2025-04-30 14:30:00'),
                    recurrenceDatesTimes: RecurrenceDateTimes::create(
                        self::createDateTime('2025-05-02 14:30:00'),
                        self::createDateTime('2025-05-04 14:30:00'),
                    )
                )
            ),
            'event' => EventOccurrenceSpecificationWasChanged::create(
                eventId: NodeAggregateIdentifier::fromString('my-event'),
                calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                occurrenceSpecification: EventOccurrenceSpecification::create(
                    startDate: self::createDateTime('2025-04-30 14:30:00'),
                    recurrenceDatesTimes: null,
                ),
                dateOfChange: self::createDateTime('2025-05-03 11:00:00'),
            ),
            'expectedEventDatesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-30 00:00:00'),
                    'endDate' => self::createDateTime('2025-05-01 00:00:00'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-30 14:30:00'),
                            self::createDateTime('2025-04-30 14:30:00'),
                        )
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-30 00:00:00'),
                    'endDate' => self::createDateTime('2025-05-03 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-30 14:30:00'),
                            self::createDateTime('2025-04-30 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-02 14:30:00'),
                            self::createDateTime('2025-05-02 14:30:00'),
                        ),
                    )
                ],
            ],
            'expectedEventDatesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-30 14:30:00'),
                            self::createDateTime('2025-04-30 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-02 14:30:00'),
                            self::createDateTime('2025-05-02 14:30:00'),
                        ),
                    )
                ]
            ]
        ];
    }

    /**
     *
     * @param list<ExpectedEventOccurrencesWithinPeriod> $expectedEventOccurrencesWithinPeriod
     * @param list<ExpectedEventOccurrencesByEventId> $expectedEventOccurrencesByEventId
     * @dataProvider passageOfTimeProvider
     */
    public function testWhenTimeHasPassed(
        EventWasCreated $previousStateEvent,
        TimeHasPassed $event,
        array $expectedEventOccurrencesWithinPeriod,
        array $expectedEventOccurrencesByEventId,
    ): void {
        $persistenceManager = $this->objectManager->get(PersistenceManager::class);
        $workspace = new Workspace('live');
        $workspaceRepository = $this->objectManager->get(WorkspaceRepository::class);
        $workspaceRepository->add($workspace);

        $nodeTypeManager = $this->objectManager->get(NodeTypeManager::class);
        $nodeData = new NodeData('/i/dont/care', $workspace, (string)$previousStateEvent->eventId);
        $nodeData->setProperty('occurrence', $previousStateEvent->occurrenceSpecification);
        $nodeData->setNodeType($nodeTypeManager->getNodeType('Sitegeist.GroundhogDay:Document.Event'));
        $persistenceManager->persistAll();

        $writeSubject = $this->objectManager->get(EventOccurrenceZookeeper::class);
        $readSubject = $this->objectManager->get(EventOccurrenceRepository::class);

        $writeSubject->whenEventWasCreated($previousStateEvent);
        $writeSubject->whenTimeHasPassed($event);

        self::assertEqualEventOccurrencesWithinPeriod($expectedEventOccurrencesWithinPeriod, $readSubject);
        self::assertEqualEventOccurrencesByEventId($expectedEventOccurrencesByEventId, $readSubject);
    }

    /**
     * @return iterable<string,array{
     *     previousStateEvent: EventWasCreated,
     *     event: TimeHasPassed,
     *     expectedEventOccurrencesWithinPeriod: list<ExpectedEventOccurrencesWithinPeriod>,
     *     expectedEventOccurrencesByEventId: list<ExpectedEventOccurrencesByEventId>,
     * }>
     */
    public static function passageOfTimeProvider(): iterable
    {
        yield 'single-day daily event, forever, passage of time' => [
            'previousStateEvent' => EventWasCreated::create(
                eventId: NodeAggregateIdentifier::fromString('my-event'),
                calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                occurrenceSpecification: EventOccurrenceSpecification::create(
                    startDate: self::createDateTime('2025-05-01 14:30:00'),
                    recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=MONTHLY;BYMONTHDAY=1'),
                )
            ),
            'event' => TimeHasPassed::create(
                self::createDateTime('2025-11-17 13:48:27'),
            ),
            'expectedEventOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTime('2025-04-30 23:59:59'),
                    'occurrences' => EventOccurrences::create(),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTime('2025-05-01 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-01 14:30:00'),
                            self::createDateTime('2025-05-01 14:30:00'),
                        )
                    ),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-30 00:00:00'),
                    'endDate' => self::createDateTime('2025-06-01 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-01 14:30:00'),
                            self::createDateTime('2025-05-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-06-01 14:30:00'),
                            self::createDateTime('2025-06-01 14:30:00'),
                        )
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-07-30 00:00:00'),
                    'endDate' => self::createDateTime('2025-09-01 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-08-01 14:30:00'),
                            self::createDateTime('2025-08-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-09-01 14:30:00'),
                            self::createDateTime('2025-09-01 14:30:00'),
                        )
                    )
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2026-10-30 00:00:00'),
                    'endDate' => self::createDateTime('2026-12-01 23:59:59'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2026-11-01 14:30:00'),
                            self::createDateTime('2026-11-01 14:30:00'),
                        )
                    )
                ],
            ],
            'expectedEventDatesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-01 14:30:00'),
                            self::createDateTime('2025-05-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-06-01 14:30:00'),
                            self::createDateTime('2025-06-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-07-01 14:30:00'),
                            self::createDateTime('2025-07-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-08-01 14:30:00'),
                            self::createDateTime('2025-08-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-09-01 14:30:00'),
                            self::createDateTime('2025-09-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-10-01 14:30:00'),
                            self::createDateTime('2025-10-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-11-01 14:30:00'),
                            self::createDateTime('2025-11-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-12-01 14:30:00'),
                            self::createDateTime('2025-12-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2026-01-01 14:30:00'),
                            self::createDateTime('2026-01-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2026-02-01 14:30:00'),
                            self::createDateTime('2026-02-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2026-03-01 14:30:00'),
                            self::createDateTime('2026-03-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2026-04-01 14:30:00'),
                            self::createDateTime('2026-04-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2026-05-01 14:30:00'),
                            self::createDateTime('2026-05-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2026-06-01 14:30:00'),
                            self::createDateTime('2026-06-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2026-07-01 14:30:00'),
                            self::createDateTime('2026-07-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2026-08-01 14:30:00'),
                            self::createDateTime('2026-08-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2026-09-01 14:30:00'),
                            self::createDateTime('2026-09-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2026-10-01 14:30:00'),
                            self::createDateTime('2026-10-01 14:30:00'),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2026-11-01 14:30:00'),
                            self::createDateTime('2026-11-01 14:30:00'),
                        ),
                    )
                ]
            ],
        ];
    }

    /**
     * @param list<ExpectedEventOccurrencesWithinPeriod> $expectedEventOccurrencesWithinPeriod
     * @param list<ExpectedEventOccurrencesByEventId> $expectedEventOccurrencesByEventId
     * @dataProvider eventRemovalProvider
     */
    public function testWhenNodeWasRemoved(
        EventWasCreated $previousStateEvent,
        EventWasRemoved $event,
        array $expectedEventOccurrencesWithinPeriod,
        array $expectedEventOccurrencesByEventId,
    ) {
        $writeSubject = $this->objectManager->get(EventOccurrenceZookeeper::class);
        $readSubject = $this->objectManager->get(EventOccurrenceRepository::class);

        $writeSubject->whenEventWasCreated($previousStateEvent);
        $writeSubject->whenEventWasRemoved($event);

        self::assertEqualEventOccurrencesWithinPeriod($expectedEventOccurrencesWithinPeriod, $readSubject);
        self::assertEqualEventOccurrencesByEventId($expectedEventOccurrencesByEventId, $readSubject);
    }

    /**
     * @return iterable<string,array{
     *     previousStateEvent: EventWasCreated,
     *     event: EventWasRemoved,
     *     expectedEventOccurrencesWithinPeriod: list<ExpectedEventOccurrencesWithinPeriod>,
     *     expectedEventOccurrencesByEventId: list<ExpectedEventOccurrencesByEventId>,
     * }>
     */
    public static function eventRemovalProvider(): iterable
    {
        yield 'single-day daily event, removed' => [
            'previousStateEvent' => EventWasCreated::create(
                eventId: NodeAggregateIdentifier::fromString('my-event'),
                calendarId: NodeAggregateIdentifier::fromString('my-calendar'),
                occurrenceSpecification: EventOccurrenceSpecification::create(
                    startDate: self::createDateTime('2025-04-24 14:30:00'),
                    recurrenceRule: RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=5'),
                )
            ),
            'event' => new EventWasRemoved(
                NodeAggregateIdentifier::fromString('my-event'),
            ),
            'expectedEventOccurrencesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTime('2025-04-23 23:59:59'),
                    'occurrences' => EventOccurrences::create(),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTime('2025-04-24 23:59:59'),
                    'occurrences' => EventOccurrences::create(),
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-05-03 00:00:00'),
                    'endDate' => self::createDateTime('2025-05-16 23:59:59'),
                    'occurrences' => EventOccurrences::create(),
                ],
            ],
            'expectedEventDatesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'occurrences' => EventOccurrences::create(),
                ]
            ]
        ];
    }

    /**
     * @param list<ExpectedEventOccurrencesWithinPeriod> $expectedEventOccurrencesWithinPeriod
     */
    private static function assertEqualEventOccurrencesWithinPeriod(array $expectedEventOccurrencesWithinPeriod, EventOccurrenceRepository $readSubject, ?\DateTimeZone $timeZone = null): void
    {
        foreach ($expectedEventOccurrencesWithinPeriod as $testRecord) {
            $expected = iterator_to_array($testRecord['occurrences']);
            $actual = iterator_to_array($readSubject->findEventOccurrencesWithinPeriod($testRecord['calendarId'], $testRecord['startDate'], $testRecord['endDate']));
            Assert::assertEquals(
                $expected,
                $actual,
                'Expected occurrences ' . PHP_EOL . \json_encode($expected) . PHP_EOL
                . ', got ' . PHP_EOL . \json_encode($actual) . PHP_EOL
                . ' for period from ' . $testRecord['startDate']->format('Y-m-d H:i:s') . ' to ' . $testRecord['endDate']->format('Y-m-d H:i:s'),
            );
        }
    }
    /**
     * @param list<ExpectedEventOccurrencesWithinPeriod> $expectedEventOccurrencesByEventId
     */
    private static function assertEqualEventOccurrencesByEventId(array $expectedEventOccurrencesByEventId, EventOccurrenceRepository $readSubject, ?\DateTimeZone $timeZone = null): void
    {
        foreach ($expectedEventOccurrencesByEventId as $testRecord) {
            $expected = iterator_to_array($testRecord['occurrences']);
            $actual = iterator_to_array($readSubject->findEventOccurrencesByEventId($testRecord['eventId'], $timeZone));
            Assert::assertEquals(
                $expected,
                $actual,
                'Expected occurrences ' . PHP_EOL . \json_encode($expected) . PHP_EOL
                . ', got ' . PHP_EOL . \json_encode($actual) . PHP_EOL
                . ' for event ' . $testRecord['eventId'],
            );
        }
    }

    private static function createDateTime(string $date, ?\DateTimeZone $timeZone = null): \DateTimeImmutable
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
