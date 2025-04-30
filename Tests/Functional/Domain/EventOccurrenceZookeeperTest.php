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
use Sitegeist\GroundhogDay\Domain\EventOccurrenceSpecification;
use Sitegeist\GroundhogDay\Domain\EventOccurrenceZookeeper;
use Sitegeist\GroundhogDay\Domain\EventWasRemoved;
use Sitegeist\GroundhogDay\Domain\Recurrence\RecurrenceDates;
use Sitegeist\GroundhogDay\Domain\Recurrence\RecurrenceDatesWereChanged;
use Sitegeist\GroundhogDay\Domain\Recurrence\RecurrenceRule;
use Sitegeist\GroundhogDay\Domain\Recurrence\RecurrenceRuleWasChanged;
use Sitegeist\GroundhogDay\Domain\TimeHasPassed;

final class EventOccurrenceZookeeperTest extends FunctionalTestCase
{
    protected static $testablePersistenceEnabled = true;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpPersistence();
    }

    /**
     * @param iterable<RecurrenceRuleWasChanged> $previousEvents
     * @param list<array{calendarId: NodeAggregateIdentifier, startDate: \DateTimeImmutable, endDate: \DateTimeImmutable, eventDates: list<EventOccurrence>}> $expectedEventDatesWithinPeriod
     * @param list<array{eventId: NodeAggregateIdentifier, eventDates: list<EventOccurrence>}> $expectedEventDatesByEventId
     * @param array{identifier: string, recurrenceRule: string, startDate: \DateTimeImmutable}|null $requiredNodeData
     * @dataProvider recurrenceRuleChangeProvider
     */
    public function testWhenRecurrenceRuleWasChanged(
        array $previousEvents,
        RecurrenceRuleWasChanged|EventWasRemoved|TimeHasPassed $event,
        array $expectedEventDatesWithinPeriod,
        array $expectedEventDatesByEventId,
        ?array $requiredNodeData = null,
    ): void {
        if ($requiredNodeData !== null) {
            $persistenceManager = $this->objectManager->get(PersistenceManager::class);
            $workspace = new Workspace('live');
            $workspaceRepository = $this->objectManager->get(WorkspaceRepository::class);
            $workspaceRepository->add($workspace);

            $nodeTypeManager = $this->objectManager->get(NodeTypeManager::class);
            $nodeData = new NodeData('/i/dont/care', $workspace, $requiredNodeData['identifier']);
            $nodeData->setProperty('occurrence', new EventOccurrenceSpecification(
                $requiredNodeData['startDate'],
                null,
                RecurrenceRule::fromString($requiredNodeData['recurrenceRule']),
                null,
            ));
            $nodeData->setNodeType($nodeTypeManager->getNodeType('Sitegeist.GroundhogDay:Document.Event'));
            $persistenceManager->persistAll();
        }
        $writeSubject = $this->objectManager->get(EventOccurrenceZookeeper::class);
        $readSubject = $this->objectManager->get(EventOccurrenceRepository::class);
        foreach ($previousEvents as $previousEvent) {
            $writeSubject->whenRecurrenceRuleWasChanged($previousEvent);
        }

        match (get_class($event)) {
            RecurrenceRuleWasChanged::class => $writeSubject->whenRecurrenceRuleWasChanged($event),
            EventWasRemoved::class => $writeSubject->whenEventWasRemoved($event),
            TimeHasPassed::class => $writeSubject->whenTimeHasPassed($event),
        };

        foreach ($expectedEventDatesWithinPeriod as $testRecord) {
            $expected = $testRecord['eventDates'];
            $actual = iterator_to_array($readSubject->findEventOccurrencesWithinPeriod($testRecord['calendarId'], $testRecord['startDate'], $testRecord['endDate']));
            Assert::assertEquals(
                $expected,
                $actual,
                'Expected occurrences ' . PHP_EOL . \json_encode($expected) . PHP_EOL
                . ', got ' . PHP_EOL . \json_encode($actual) . PHP_EOL
                . ' for range ' . $testRecord['startDate']->format('Y-m-d H:i:s') . ' to ' . $testRecord['endDate']->format('Y-m-d H:i:s'),
            );
        }

        foreach ($expectedEventDatesByEventId as $testRecord) {
            $expected = $testRecord['eventDates'];
            $actual = iterator_to_array($readSubject->findEventOccurrencesByEventId($testRecord['eventId']));
            Assert::assertEquals(
                $expected,
                $actual,
                'Expected occurrences ' . PHP_EOL . \json_encode($expected) . PHP_EOL
                . ', got ' . PHP_EOL . \json_encode($actual) . PHP_EOL
                . ' for event ' . $testRecord['eventId'],
            );
        }
    }

    /**
     * @return iterable<string,array{
     *     previousEvents: array<RecurrenceRuleWasChanged>,
     *     event: RecurrenceRuleWasChanged,
     *     expectedEventDatesWithinPeriod: list<array{startDate: \DateTimeImmutable, endDate: \DateTimeImmutable, eventDates: list<EventOccurrence>}>,
     *     expectedEventDatesByEventId: list<array{eventId: NodeAggregateIdentifier, eventDates: list<EventOccurrence>}>,
     *     requiredNodeData?: array{identifier: string, recurrenceRule: string}
     * }>
     */
    public static function recurrenceRuleChangeProvider(): iterable
    {
        yield 'single-day daily event, initial recurrence rule' => [
            'previousEvents' => [],
            'event' => new RecurrenceRuleWasChanged(
                NodeAggregateIdentifier::fromString('my-calendar'),
                NodeAggregateIdentifier::fromString('my-event'),
                RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=5'),
                self::createDateTime('2025-04-24 14:30:00', true),
                null,
                self::createDateTime('2025-04-24 00:00:00'),
            ),
            'expectedEventDatesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTime('2025-04-23 23:59:59'),
                    'eventDates' => []
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTime('2025-04-24 23:59:59'),
                    'eventDates' => [
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-24 14:30:00'),
                            self::createDateTime('2025-04-24 14:30:00'),
                        )
                    ]
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTime('2025-05-08 23:59:59'),
                    'eventDates' => [
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
                    ]
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-05-25 00:00:00'),
                    'endDate' => self::createDateTime('2025-06-03 23:59:59'),
                    'eventDates' => [
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-06-03 14:30:00'),
                            self::createDateTime('2025-06-03 14:30:00'),
                        )
                    ]
                ],
            ],
            'expectedEventDatesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'eventDates' => [
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
                    ]
                ]
            ]
        ];

        yield 'single-day daily event, changed recurrence rule' => [
            'previousEvents' => [
                new RecurrenceRuleWasChanged(
                    NodeAggregateIdentifier::fromString('my-calendar'),
                    NodeAggregateIdentifier::fromString('my-event'),
                    RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=5'),
                    self::createDateTime('2025-04-24 14:30:00', true),
                    null,
                    self::createDateTime('2025-04-24 00:00:00'),
                )
            ],
            'event' => new RecurrenceRuleWasChanged(
                NodeAggregateIdentifier::fromString('my-calendar'),
                NodeAggregateIdentifier::fromString('my-event'),
                RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=7;COUNT=5'),
                self::createDateTime('2025-04-24 15:00:00', true),
                null,
                self::createDateTime('2025-05-14 14:00:00'),
            ),
            'expectedEventDatesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTime('2025-04-23 23:59:59'),
                    'eventDates' => []
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTime('2025-04-24 23:59:59'),
                    'eventDates' => [
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-24 14:30:00'),
                            self::createDateTime('2025-04-24 14:30:00'),
                        )
                    ]
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-05-03 00:00:00'),
                    'endDate' => self::createDateTime('2025-05-16 23:59:59'),
                    'eventDates' => [
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
                    ]
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-05-21 00:00:00'),
                    'endDate' => self::createDateTime('2025-06-03 23:59:59'),
                    'eventDates' => [
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-22 15:00:00'),
                            self::createDateTime('2025-05-22 15:00:00'),
                        )
                    ]
                ],
            ],
            'expectedEventDatesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'eventDates' => [
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
                    ]
                ]
            ]
        ];

        yield 'single-day daily event, removed recurrence rule' => [
            'previousEvents' => [
                new RecurrenceRuleWasChanged(
                    NodeAggregateIdentifier::fromString('my-calendar'),
                    NodeAggregateIdentifier::fromString('my-event'),
                    RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=5'),
                    self::createDateTime('2025-04-24 14:30:00', true),
                    null,
                    self::createDateTime('2025-04-24 00:00:00'),
                )
            ],
            'event' => new RecurrenceRuleWasChanged(
                NodeAggregateIdentifier::fromString('my-calendar'),
                NodeAggregateIdentifier::fromString('my-event'),
                null,
                self::createDateTime('2025-04-24 14:30:00', true),
                null,
                self::createDateTime('2025-05-14 14:00:00'),
            ),
            'expectedEventDatesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTime('2025-04-23 23:59:59'),
                    'eventDates' => []
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTime('2025-04-24 23:59:59'),
                    'eventDates' => [
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-24 14:30:00'),
                            self::createDateTime('2025-04-24 14:30:00'),
                        )
                    ]
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-05-03 00:00:00'),
                    'endDate' => self::createDateTime('2025-05-16 23:59:59'),
                    'eventDates' => [
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-04 14:30:00'),
                            self::createDateTime('2025-05-04 14:30:00'),
                        )
                    ]
                ],
            ],
            'expectedEventDatesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'eventDates' => [
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
                    ]
                ]
            ]
        ];

        yield 'single-day daily event, removed event' => [
            'previousEvents' => [
                new RecurrenceRuleWasChanged(
                    NodeAggregateIdentifier::fromString('my-calendar'),
                    NodeAggregateIdentifier::fromString('my-event'),
                    RecurrenceRule::fromString('RRULE:FREQ=DAILY;INTERVAL=10;COUNT=5'),
                    self::createDateTime('2025-04-24 14:30:00', true),
                    null,
                    self::createDateTime('2025-04-24 00:00:00'),
                )
            ],
            'event' => new EventWasRemoved(
                NodeAggregateIdentifier::fromString('my-event'),
            ),
            'expectedEventDatesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTime('2025-04-23 23:59:59'),
                    'eventDates' => []
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTime('2025-04-24 23:59:59'),
                    'eventDates' => []
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-05-03 00:00:00'),
                    'endDate' => self::createDateTime('2025-05-16 23:59:59'),
                    'eventDates' => []
                ],
            ],
            'expectedEventDatesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'eventDates' => []
                ]
            ]
        ];

        yield 'single-day daily event, forever' => [
            'previousEvents' => [],
            'event' => new RecurrenceRuleWasChanged(
                NodeAggregateIdentifier::fromString('my-calendar'),
                NodeAggregateIdentifier::fromString('my-event'),
                RecurrenceRule::fromString('RRULE:FREQ=MONTHLY;BYMONTHDAY=1'),
                self::createDateTime('2025-05-01 14:30:00', true),
                null,
                self::createDateTime('2025-04-24 00:00:00'),
            ),
            'expectedEventDatesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTime('2025-04-30 23:59:59'),
                    'eventDates' => []
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTime('2025-05-01 23:59:59'),
                    'eventDates' => [
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-01 14:30:00'),
                            self::createDateTime('2025-05-01 14:30:00'),
                        )
                    ]
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-30 00:00:00'),
                    'endDate' => self::createDateTime('2025-06-01 23:59:59'),
                    'eventDates' => [
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
                    ]
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2026-03-30 00:00:00'),
                    'endDate' => self::createDateTime('2026-05-01 23:59:59'),
                    'eventDates' => [
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2026-04-01 14:30:00'),
                            self::createDateTime('2026-04-01 14:30:00'),
                        )
                    ]
                ],
            ],
            'expectedEventDatesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'eventDates' => [
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
                    ]
                ]
            ]
        ];

        yield 'single-day daily event, forever, passage of time' => [
            'previousEvents' => [
                new RecurrenceRuleWasChanged(
                    NodeAggregateIdentifier::fromString('my-calendar'),
                    NodeAggregateIdentifier::fromString('my-event'),
                    RecurrenceRule::fromString('RRULE:FREQ=MONTHLY;BYMONTHDAY=1'),
                    self::createDateTime('2025-05-01 14:30:00'),
                    null,
                    self::createDateTime('2025-04-24 00:00:00'),
                )
            ],
            'event' => new TimeHasPassed(
                self::createDateTime('2025-11-17 13:48:27'),
            ),
            'expectedEventDatesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTime('2025-04-30 23:59:59'),
                    'eventDates' => []
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-18 00:00:00'),
                    'endDate' => self::createDateTime('2025-05-01 23:59:59'),
                    'eventDates' => [
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-01 14:30:00'),
                            self::createDateTime('2025-05-01 14:30:00'),
                        )
                    ]
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-30 00:00:00'),
                    'endDate' => self::createDateTime('2025-06-01 23:59:59'),
                    'eventDates' => [
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
                    ]
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-07-30 00:00:00'),
                    'endDate' => self::createDateTime('2025-09-01 23:59:59'),
                    'eventDates' => [
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
                    ]
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2026-10-30 00:00:00'),
                    'endDate' => self::createDateTime('2026-12-01 23:59:59'),
                    'eventDates' => [
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2026-11-01 14:30:00'),
                            self::createDateTime('2026-11-01 14:30:00'),
                        )
                    ]
                ],
            ],
            'expectedEventDatesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'eventDates' => [
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
                    ]
                ]
            ],
            'requiredNodeData' => [
                'identifier' => 'my-event',
                'startDate' => self::createDateTime('2025-05-01 14:30:00', true),
                'recurrenceRule' => 'RRULE:FREQ=MONTHLY;BYMONTHDAY=1'
            ],
        ];
    }

    /**
     * @param iterable<RecurrenceDatesWereChanged> $previousEvents
     * @param list<array{calendarId: NodeAggregateIdentifier, startDate: \DateTimeImmutable, endDate: \DateTimeImmutable, eventDates: list<EventOccurrence>}> $expectedEventDatesWithinPeriod
     * @param list<array{eventId: NodeAggregateIdentifier, eventDates: list<EventOccurrence>}> $expectedEventDatesByEventId
     * @dataProvider recurrenceDatesChangeProvider
     */
    public function testWhenRecurrenceDatesWereChanged(
        array $previousEvents,
        RecurrenceDatesWereChanged $event,
        array $expectedEventDatesWithinPeriod,
        array $expectedEventDatesByEventId,
    ) {
        $writeSubject = $this->objectManager->get(EventOccurrenceZookeeper::class);
        $readSubject = $this->objectManager->get(EventOccurrenceRepository::class);
        foreach ($previousEvents as $previousEvent) {
            $writeSubject->whenRecurrenceDatesWereChanged($previousEvent);
        }

        $writeSubject->whenRecurrenceDatesWereChanged($event);

        foreach ($expectedEventDatesWithinPeriod as $testRecord) {
            $expected = $testRecord['eventDates'];
            $actual = iterator_to_array($readSubject->findEventOccurrencesWithinPeriod($testRecord['calendarId'], $testRecord['startDate'], $testRecord['endDate']));
            Assert::assertEquals(
                $expected,
                $actual,
                'Expected occurrences ' . PHP_EOL . \json_encode($expected) . PHP_EOL
                . ', got ' . PHP_EOL . \json_encode($actual) . PHP_EOL
                . ' for range ' . $testRecord['startDate']->format('Y-m-d H:i:s') . ' to ' . $testRecord['endDate']->format('Y-m-d H:i:s'),
            );
        }

        foreach ($expectedEventDatesByEventId as $testRecord) {
            $expected = $testRecord['eventDates'];
            $actual = iterator_to_array($readSubject->findEventOccurrencesByEventId($testRecord['eventId']));
            Assert::assertEquals(
                $expected,
                $actual,
                'Expected occurrences ' . PHP_EOL . \json_encode($expected) . PHP_EOL
                . ', got ' . PHP_EOL . \json_encode($actual) . PHP_EOL
                . ' for event ' . $testRecord['eventId'],
            );
        }
    }

    /**
     * @return iterable<string,array{
     *     previousEvents: array<RecurrenceDatesWereChanged>,
     *     event: RecurrenceDatesWereChanged,
     *     expectedEventDatesWithinPeriod: list<array{startDate: \DateTimeImmutable, endDate: \DateTimeImmutable, eventDates: list<EventOccurrence>}>,
     *     expectedEventDatesByEventId: list<array{eventId: NodeAggregateIdentifier, eventDates: list<EventOccurrence>}>,
     * }>
     */
    public static function recurrenceDatesChangeProvider(): iterable
    {
        yield 'single-day event, initial recurrence dates' => [
            'previousEvents' => [],
            'event' => new RecurrenceDatesWereChanged(
                NodeAggregateIdentifier::fromString('my-calendar'),
                NodeAggregateIdentifier::fromString('my-event'),
                self::createDateTime('2025-04-30 14:30:00', true),
                null,
                RecurrenceDates::create(
                    self::createDateTime('2025-05-02 14:30:00', true),
                ),
                self::createDateTime('2025-04-30 10:00:00'),
            ),
            'expectedEventDatesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-30 00:00:00', true),
                    'endDate' => self::createDateTime('2025-05-01 00:00:00', true),
                    'eventDates' => [
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-30 14:30:00', true),
                            self::createDateTime('2025-04-30 14:30:00', true),
                        )
                    ]
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-30 00:00:00', true),
                    'endDate' => self::createDateTime('2025-05-03 00:00:00', true),
                    'eventDates' => [
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-30 14:30:00', true),
                            self::createDateTime('2025-04-30 14:30:00', true),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-02 14:30:00', true),
                            self::createDateTime('2025-05-02 14:30:00', true),
                        ),
                    ]
                ],
            ],
            'expectedEventDatesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'eventDates' => [
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-30 14:30:00', true),
                            self::createDateTime('2025-04-30 14:30:00', true),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-02 14:30:00', true),
                            self::createDateTime('2025-05-02 14:30:00', true),
                        ),
                    ]
                ]
            ]
        ];

        yield 'single-day event, changed recurrence dates' => [
            'previousEvents' => [
                new RecurrenceDatesWereChanged(
                    NodeAggregateIdentifier::fromString('my-calendar'),
                    NodeAggregateIdentifier::fromString('my-event'),
                    self::createDateTime('2025-04-30 14:30:00', true),
                    null,
                    RecurrenceDates::create(
                        self::createDateTime('2025-05-02 14:30:00', true),
                    ),
                    self::createDateTime('2025-04-30 10:00:00'),
                )
            ],
            'event' => new RecurrenceDatesWereChanged(
                NodeAggregateIdentifier::fromString('my-calendar'),
                NodeAggregateIdentifier::fromString('my-event'),
                self::createDateTime('2025-04-30 14:30:00', true),
                null,
                RecurrenceDates::create(
                    self::createDateTime('2025-05-02 15:30:00', true),
                    self::createDateTime('2025-05-03 14:30:00', true),
                ),
                self::createDateTime('2025-04-30 11:00:00'),
            ),
            'expectedEventDatesWithinPeriod' => [
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-30 00:00:00', true),
                    'endDate' => self::createDateTime('2025-05-01 00:00:00', true),
                    'eventDates' => [
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-30 14:30:00', true),
                            self::createDateTime('2025-04-30 14:30:00', true),
                        )
                    ]
                ],
                [
                    'calendarId' => NodeAggregateIdentifier::fromString('my-calendar'),
                    'startDate' => self::createDateTime('2025-04-30 00:00:00', true),
                    'endDate' => self::createDateTime('2025-05-03 00:00:00', true),
                    'eventDates' => [
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-30 14:30:00', true),
                            self::createDateTime('2025-04-30 14:30:00', true),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-02 15:30:00', true),
                            self::createDateTime('2025-05-02 15:30:00', true),
                        ),
                    ]
                ],
            ],
            'expectedEventDatesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'eventDates' => [
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-30 14:30:00', true),
                            self::createDateTime('2025-04-30 14:30:00', true),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-02 15:30:00', true),
                            self::createDateTime('2025-05-02 15:30:00', true),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-03 14:30:00', true),
                            self::createDateTime('2025-05-03 14:30:00', true),
                        ),
                    ]
                ]
            ]
        ];

        yield 'single-day event, removed recurrence dates' => [
            'previousEvents' => [
                new RecurrenceDatesWereChanged(
                    NodeAggregateIdentifier::fromString('my-calendar'),
                    NodeAggregateIdentifier::fromString('my-event'),
                    self::createDateTime('2025-04-30 14:30:00', true),
                    null,
                    RecurrenceDates::create(
                        self::createDateTime('2025-05-02 14:30:00', true),
                        self::createDateTime('2025-05-04 14:30:00', true),
                    ),
                    self::createDateTime('2025-04-30 10:00:00'),
                )
            ],
            'event' => new RecurrenceDatesWereChanged(
                NodeAggregateIdentifier::fromString('my-calendar'),
                NodeAggregateIdentifier::fromString('my-event'),
                self::createDateTime('2025-04-30 14:30:00', true),
                null,
                null,
                self::createDateTime('2025-05-03 11:00:00'),
            ),
            'expectedEventDatesWithinPeriod' => [
                [
                    'startDate' => self::createDateTime('2025-04-30 00:00:00', true),
                    'endDate' => self::createDateTime('2025-05-01 00:00:00', true),
                    'eventDates' => [
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-30 14:30:00', true),
                            self::createDateTime('2025-04-30 14:30:00', true),
                        )
                    ]
                ],
                [
                    'startDate' => self::createDateTime('2025-04-30 00:00:00', true),
                    'endDate' => self::createDateTime('2025-05-03 23:59:59', true),
                    'eventDates' => [
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-30 14:30:00', true),
                            self::createDateTime('2025-04-30 14:30:00', true),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-02 15:30:00', true),
                            self::createDateTime('2025-05-02 15:30:00', true),
                        ),
                    ]
                ],
            ],
            'expectedEventDatesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'eventDates' => [
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-30 14:30:00', true),
                            self::createDateTime('2025-04-30 14:30:00', true),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-02 15:30:00', true),
                            self::createDateTime('2025-05-02 15:30:00', true),
                        ),
                    ]
                ]
            ]
        ];
    }

    private static function createDateTime(string $date, bool $inBerlin = false): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date, $inBerlin ? new \DateTimeZone('Europe/Berlin') : null);
    }

    private function setUpPersistence(): void
    {
        /** @var DoctrineService $doctrineService */
        $doctrineService = $this->objectManager->get(DoctrineService::class);
        $doctrineService->executeMigrations();
    }
}
