<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Tests\Functional\Domain;

use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\Flow\Persistence\Doctrine\Service as DoctrineService;
use Neos\Flow\Tests\FunctionalTestCase;
use PHPUnit\Framework\Assert;
use Sitegeist\GroundhogDay\Domain\EventOccurrence;
use Sitegeist\GroundhogDay\Domain\EventOccurrenceRepository;
use Sitegeist\GroundhogDay\Domain\EventOccurrenceZookeeper;
use Sitegeist\GroundhogDay\Domain\Recurrence\RecurrenceRule;
use Sitegeist\GroundhogDay\Domain\Recurrence\RecurrenceRuleWasChanged;

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
     * @param list<array{startDate: \DateTimeImmutable, endDate: \DateTimeImmutable, eventDates: list<EventOccurrence>}> $expectedEventDatesWithinPeriod
     * @param list<array{eventId: NodeAggregateIdentifier, eventDates: list<EventOccurrence>}> $expectedEventDatesByEventId
     * @dataProvider recurrenceRuleChangeProvider
     */
    public function testWhenRecurrenceRuleWasChanged(array $previousEvents, RecurrenceRuleWasChanged $event, array $expectedEventDatesWithinPeriod, array $expectedEventDatesByEventId): void
    {
        $writeSubject = $this->objectManager->get(EventOccurrenceZookeeper::class);
        $readSubject = $this->objectManager->get(EventOccurrenceRepository::class);
        foreach ($previousEvents as $previousEvent) {
            $writeSubject->whenRecurrenceRuleWasChanged($previousEvent);
        }

        $writeSubject->whenRecurrenceRuleWasChanged($event);

        foreach ($expectedEventDatesWithinPeriod as $testRecord) {
            $expected = $testRecord['eventDates'];
            $actual = iterator_to_array($readSubject->findEventOccurrencesWithinPeriod($testRecord['startDate'], $testRecord['endDate']));
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
            $actual = iterator_to_array($readSubject->findEventDatesByEventId($testRecord['eventId']));
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
     *     expectedEventDatesByEventId: list<array{eventId: NodeAggregateIdentifier, eventDates: list<EventOccurrence>}>
     * }>
     */
    public static function recurrenceRuleChangeProvider(): iterable
    {
        yield 'single-day daily event, initial recurrence rule' => [
            'previousEvents' => [],
            'event' => new RecurrenceRuleWasChanged(
                NodeAggregateIdentifier::fromString('my-event'),
                RecurrenceRule::fromString('DTSTART;TZID=Europe/Berlin:20250424T143000
RRULE:FREQ=DAILY;INTERVAL=10;COUNT=5'),
                self::createDateTime('2025-04-24 00:00:00'),
            ),
            'expectedEventDatesWithinPeriod' => [
                [
                    'startDate' => self::createDateTime('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTime('2025-04-23 23:59:59'),
                    'eventDates' => []
                ],
                [
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
                    NodeAggregateIdentifier::fromString('my-event'),
                    RecurrenceRule::fromString('DTSTART;TZID=Europe/Berlin:20250424T143000
RRULE:FREQ=DAILY;INTERVAL=10;COUNT=5'),
                    self::createDateTime('2025-04-24 00:00:00'),
                )
            ],
            'event' => new RecurrenceRuleWasChanged(
                NodeAggregateIdentifier::fromString('my-event'),
                RecurrenceRule::fromString('DTSTART;TZID=Europe/Berlin:20250424T150000
RRULE:FREQ=DAILY;INTERVAL=7;COUNT=5'),
                self::createDateTime('2025-05-14 14:00:00'),
            ),
            'expectedEventDatesWithinPeriod' => [
                [
                    'startDate' => self::createDateTime('2025-04-17 00:00:00'),
                    'endDate' => self::createDateTime('2025-04-23 23:59:59'),
                    'eventDates' => []
                ],
                [
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
