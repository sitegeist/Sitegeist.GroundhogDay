<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain;

use Doctrine\DBAL\Connection;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Doctrine\ConnectionFactory;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\Constraint\BeforeConstraint;
use Sitegeist\GroundhogDay\Domain\Recurrence\RecurrenceRule;

#[Flow\Scope('singleton')]
final class EventDateRepository
{
    private const TABLE_NAME = 'sitegeist_groundhogday_domain_eventdate';

    private readonly Connection $databaseConnection;

    public function __construct(
        ConnectionFactory $connectionFactory,
    ) {
        $this->databaseConnection = $connectionFactory->create();
    }

    /**
     * @return iterable<int,EventDate>
     */
    public function findEventDatesWithinPeriod(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): iterable
    {
        $rows = $this->databaseConnection->executeQuery(
            'SELECT * FROM ' . self::TABLE_NAME
             . ' WHERE date >= :startDate AND date <= :endDate',
            [
                'startDate' => $startDate->format('Y-m-d'),
                'endDate' => $endDate->format('Y-m-d')
            ]
        )->fetchAllAssociative();

        foreach ($rows as $row) {
            yield $this->mapDatabaseRowToEventDate($row);
        }
    }

    /**
     * @return iterable<int,EventDate>
     */
    public function findEventDatesByEventId(NodeAggregateIdentifier $eventId): iterable
    {
        $rows = $this->databaseConnection->executeQuery(
            'SELECT * FROM ' . self::TABLE_NAME
            . ' WHERE event_id = :eventId',
            [
                'eventId' => (string)$eventId,
            ]
        )->fetchAllAssociative();

        foreach ($rows as $row) {
            yield $this->mapDatabaseRowToEventDate($row);
        }
    }

    public function removeAllFutureEventDatesByEventId(NodeAggregateIdentifier $eventId, \DateTimeImmutable $now): void
    {
        $this->databaseConnection->executeStatement(
            'DELETE FROM ' . self::TABLE_NAME . ' WHERE event_id = :eventId AND date > :now',
            [
                'eventId' => (string)$eventId,
                'now' => $now->format('Y-m-d'),
            ]
        );
    }

    public function replaceAllFutureEventDatesByEventId(NodeAggregateIdentifier $eventId, RecurrenceRule $recurrenceRule, \DateTimeImmutable $now): void
    {
        $renderer = new ArrayTransformer();
        /** @var list<EventDate> $futureDates */
        $futureDates = [];

        foreach (
            $renderer->transform(
                $recurrenceRule->value,
                /** @todo make configurable */
                new BeforeConstraint($now->add(new \DateInterval('P1Y')))
            ) as $recurrence
        ) {
            $startDate = $recurrence->getStart();
            if ($startDate instanceof \DateTime) {
                $startDate = \DateTimeImmutable::createFromMutable($startDate);
            }
            if (!$startDate instanceof \DateTimeImmutable) {
                continue;
            }

            $futureDates[] = new EventDate(
                $eventId,
                $startDate->setTime(0, 0),
                1
            );
        }

        $this->databaseConnection->transactional(function () use ($eventId, $recurrenceRule, $now, $futureDates) {
            $this->databaseConnection->executeStatement(
                'DELETE FROM ' . self::TABLE_NAME . ' WHERE event_id = :eventId AND date > :now',
                [
                    'eventId' => (string)$eventId,
                    'now' => $now->format('Y-m-d'),
                ]
            );

            foreach ($futureDates as $futureDate) {
                $this->databaseConnection->insert(
                    self::TABLE_NAME,
                    [
                        'event_id' => (string)$eventId,
                        'date' => $futureDate->date->format('Y-m-d'),
                        'day_of_event' => 1,
                    ]
                );
            }
        });
    }

    /**
     * @param array{event_id: string, date: string, day_of_event: int} $row
     */
    private function mapDatabaseRowToEventDate(array $row): EventDate
    {
        return new EventDate(
            NodeAggregateIdentifier::fromString($row['event_id']),
            \DateTimeImmutable::createFromFormat('Y-m-d', $row['date'])->setTime(0, 0),
            (int) $row['day_of_event']
        );
    }
}
