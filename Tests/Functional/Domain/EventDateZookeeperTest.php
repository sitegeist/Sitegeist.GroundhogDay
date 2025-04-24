<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Tests\Functional\Domain;

use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\Flow\Persistence\Doctrine\Service as DoctrineService;
use Neos\Flow\Tests\FunctionalTestCase;
use PHPUnit\Framework\Assert;
use Sitegeist\GroundhogDay\Domain\EventDate;
use Sitegeist\GroundhogDay\Domain\EventDateRepository;
use Sitegeist\GroundhogDay\Domain\EventDateZookeeper;
use Sitegeist\GroundhogDay\Domain\Recurrence\RecurrenceRule;
use Sitegeist\GroundhogDay\Domain\Recurrence\RecurrenceRuleWasChanged;

final class EventDateZookeeperTest extends FunctionalTestCase
{
    protected static $testablePersistenceEnabled = true;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpPersistence();
    }

    /**
     * @param iterable<RecurrenceRuleWasChanged> $previousEvents
     * @param list<array{startDate: \DateTimeImmutable, endDate: \DateTimeImmutable, eventDates: list<EventDate>}> $expectedEventDatesWithinPeriod
     * @param list<array{eventId: NodeAggregateIdentifier, eventDates: list<EventDate>}> $expectedEventDatesByEventId
     * @dataProvider recurrenceRuleChangeProvider
     */
    public function testWhenRecurrenceRuleWasChanged(array $previousEvents, RecurrenceRuleWasChanged $event, array $expectedEventDatesWithinPeriod, array $expectedEventDatesByEventId): void
    {
        $writeSubject = $this->objectManager->get(EventDateZookeeper::class);
        $readSubject = $this->objectManager->get(EventDateRepository::class);
        foreach ($previousEvents as $previousEvent) {
            $writeSubject->whenRecurrenceRuleWasChanged($previousEvent);
        }

        $writeSubject->whenRecurrenceRuleWasChanged($event);

        foreach ($expectedEventDatesWithinPeriod as $testRecord) {
            Assert::assertEquals($testRecord['eventDates'], iterator_to_array($readSubject->findEventDatesWithinPeriod($testRecord['startDate'], $testRecord['endDate'])));
        }

        foreach ($expectedEventDatesByEventId as $testRecord) {
            Assert::assertEquals($testRecord['eventDates'], iterator_to_array($readSubject->findEventDatesByEventId($testRecord['eventId'])));
        }
    }

    /**
     * @return iterable<string,array{
     *     previousEvents: array<RecurrenceRuleWasChanged>,
     *     event: RecurrenceRuleWasChanged,
     *     expectedEventDatesWithinPeriod: list<array{startDate: \DateTimeImmutable, endDate: \DateTimeImmutable, eventDates: list<EventDate>}>,
     *     expectedEventDatesByEventId: list<array{eventId: NodeAggregateIdentifier, eventDates: list<EventDate>}>
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
                        new EventDate(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-24', false),
                            1,
                        )
                    ]
                ],
                [
                    'startDate' => self::createDateTime('2025-04-18', false),
                    'endDate' => self::createDateTime('2025-05-08', true),
                    'eventDates' => [
                        new EventDate(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-24', false),
                            1,
                        ),
                        new EventDate(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-04', false),
                            1,
                        )
                    ]
                ],
                [
                    'startDate' => self::createDateTime('2025-05-25', false),
                    'endDate' => self::createDateTime('2025-06-03', true),
                    'eventDates' => [
                        new EventDate(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-06-03', false),
                            1,
                        )
                    ]
                ],
            ],
            'expectedEventDatesByEventId' => [
                [
                    'eventId' => NodeAggregateIdentifier::fromString('my-event'),
                    'eventDates' => [
                        new EventDate(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-04-24', false),
                            1,
                        ),
                        new EventDate(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-04', false),
                            1,
                        ),
                        new EventDate(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-14', false),
                            1,
                        ),
                        new EventDate(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-05-24', false),
                            1,
                        ),
                        new EventDate(
                            NodeAggregateIdentifier::fromString('my-event'),
                            self::createDateTime('2025-06-03', false),
                            1,
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
