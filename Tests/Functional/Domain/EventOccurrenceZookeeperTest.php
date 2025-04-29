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
use Sitegeist\GroundhogDay\Domain\EventOccurrenceZookeeper;
use Sitegeist\GroundhogDay\Domain\EventWasRemoved;
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
     * @param array{identifier: string, recurrenceRule: string}|null $requiredNodeData
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
            $nodeData->setProperty('recurrenceRule', RecurrenceRule::fromString($requiredNodeData['recurrenceRule']));
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
                RecurrenceRule::fromString('DTSTART;TZID=Europe/Berlin:20250424T143000
RRULE:FREQ=DAILY;INTERVAL=10;COUNT=5'),
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
                    RecurrenceRule::fromString('DTSTART;TZID=Europe/Berlin:20250424T143000
RRULE:FREQ=DAILY;INTERVAL=10;COUNT=5'),
                    self::createDateTime('2025-04-24 00:00:00'),
                )
            ],
            'event' => new RecurrenceRuleWasChanged(
                NodeAggregateIdentifier::fromString('my-calendar'),
                NodeAggregateIdentifier::fromString('my-event'),
                RecurrenceRule::fromString('DTSTART;TZID=Europe/Berlin:20250424T150000
RRULE:FREQ=DAILY;INTERVAL=7;COUNT=5'),
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
                    RecurrenceRule::fromString('DTSTART;TZID=Europe/Berlin:20250424T143000
RRULE:FREQ=DAILY;INTERVAL=10;COUNT=5'),
                    self::createDateTime('2025-04-24 00:00:00'),
                )
            ],
            'event' => new RecurrenceRuleWasChanged(
                NodeAggregateIdentifier::fromString('my-calendar'),
                NodeAggregateIdentifier::fromString('my-event'),
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
                    RecurrenceRule::fromString('DTSTART;TZID=Europe/Berlin:20250424T143000
RRULE:FREQ=DAILY;INTERVAL=10;COUNT=5'),
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
                RecurrenceRule::fromString('DTSTART;TZID=Europe/Berlin:20250501T143000
RRULE:FREQ=MONTHLY;BYMONTHDAY=1'),
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
                    RecurrenceRule::fromString('DTSTART;TZID=Europe/Berlin:20250501T143000
RRULE:FREQ=MONTHLY;BYMONTHDAY=1'),
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
                'recurrenceRule' => 'DTSTART;TZID=Europe/Berlin:20250501T143000
RRULE:FREQ=MONTHLY;BYMONTHDAY=1'
            ],
        ];
    }

    private static function createDateTime(string $date): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date);
    }

    private function setUpPersistence(): void
    {
        /** @var DoctrineService $doctrineService */
        $doctrineService = $this->objectManager->get(DoctrineService::class);
        $doctrineService->executeMigrations();
    }
}
