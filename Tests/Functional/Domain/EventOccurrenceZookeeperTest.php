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
            Assert::assertEquals($testRecord['eventDates'], iterator_to_array($readSubject->findEventOccurrencesWithinPeriod($testRecord['startDate'], $testRecord['endDate'])));
        }

        foreach ($expectedEventDatesByEventId as $testRecord) {
            Assert::assertEquals($testRecord['eventDates'], iterator_to_array($readSubject->findEventDatesByEventId($testRecord['eventId'])));
        }
    }

    /**
     * @return iterable<string,array{
     *     previousEvents: array<RecurrenceRuleWasChanged>,
     *     event: RecurrenceRuleWasChanged,
     *     expectedEventDatesWithinPeriod: list<array{startDate: \DateTimeImmutable, endDate: \DateTimeImmutable, eventDates: list<EventOccurrence>}>,
     *     expectedEventDatesByEventId: list<array{eventId: NodeAggregateIdentifier, eventDates: list<EventOccurrence>}>
     * }>
     *
     * @todo same event multiple times on a single day
     */
    public static function recurrenceRuleChangeProvider(): iterable
    {
        $now = new \DateTimeImmutable();

        yield 'single-day daily event, initial recurrence rule' => [
            'previousEvents' => [],
            'event' => new RecurrenceRuleWasChanged(
                NodeAggregateIdentifier::fromString('my-event'),
                RecurrenceRule::fromString('DTSTART;TZID=Europe/Berlin:20250424T000000
RRULE:FREQ=DAILY;INTERVAL=10;COUNT=5'),
                $now,
            ),
            'expectedEventDatesWithinPeriod' => [
                [
                    'startDate' => self::createDateTime('2025-04-17', false),
                    'endDate' => self::createDateTime('2025-04-23', true),
                    'eventDates' => []
                ],
                [
                    'startDate' => self::createDateTime('2025-04-18', false),
                    'endDate' => self::createDateTime('2025-04-24', true),
                    'eventDates' => [
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-24', false),
                            self::createDateTime('2025-04-24', false),
                        )
                    ]
                ],
                [
                    'startDate' => self::createDateTime('2025-04-18', false),
                    'endDate' => self::createDateTime('2025-05-08', true),
                    'eventDates' => [
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-24', false),
                            self::createDateTime('2025-04-24', false),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-04', false),
                            self::createDateTime('2025-05-04', false),
                        )
                    ]
                ],
                [
                    'startDate' => self::createDateTime('2025-05-25', false),
                    'endDate' => self::createDateTime('2025-06-03', true),
                    'eventDates' => [
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-06-03', false),
                            self::createDateTime('2025-06-03', false),
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
                            self::createDateTime('2025-04-24', false),
                            self::createDateTime('2025-04-24', false),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-04', false),
                            self::createDateTime('2025-05-04', false),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-14', false),
                            self::createDateTime('2025-05-14', false),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-24', false),
                            self::createDateTime('2025-05-24', false),
                        ),
                        EventOccurrence::create(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-06-03', false),
                            self::createDateTime('2025-06-03', false),
                        ),
                    ]
                ]
            ]
        ];
    }

    private static function createDateTime(string $date, bool $end): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        $date = $end ? $date->setTime(23, 59, 59) : $date->setTime(0, 0);

        return $date;
    }
    private function setUpPersistence(): void
    {
        /** @var DoctrineService $doctrineService */
        $doctrineService = $this->objectManager->get(DoctrineService::class);
        $doctrineService->executeMigrations();
    }
}
