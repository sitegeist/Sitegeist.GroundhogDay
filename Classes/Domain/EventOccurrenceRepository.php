<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain;

use Doctrine\DBAL\Connection;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Doctrine\ConnectionFactory;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\Constraint\BeforeConstraint;
use Sitegeist\GroundhogDay\Domain\Recurrence\RecurrenceRule;

/**
 * @todo timezone support
 *
 * @phpstan-type DatabaseRow array{calendar_id: string, event_id: string, start_date: string, end_date: string}
 */
#[Flow\Scope('singleton')]
final class EventOccurrenceRepository
{
    private const TABLE_NAME = 'sitegeist_groundhogday_domain_event_occurrence';

    private const DATE_FORMAT = 'Y-m-d H:i:s';

    private readonly Connection $databaseConnection;

    public function __construct(
        ConnectionFactory $connectionFactory,
    ) {
        $this->databaseConnection = $connectionFactory->create();
    }

    /**
     * @return iterable<int,EventOccurrence>
     */
    public function findEventOccurrencesWithinPeriod(NodeAggregateIdentifier $calendarId, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): iterable
    {
        /** @var array<int,DatabaseRow> $rows */
        $rows = $this->databaseConnection->executeQuery(
            'SELECT * FROM ' . self::TABLE_NAME
             . ' WHERE calendar_id = :calendarId AND start_date <= :endDate AND end_date >= :startDate',
            [
                'calendarId' => (string)$calendarId,
                'startDate' => $startDate->format(self::DATE_FORMAT),
                'endDate' => $endDate->format(self::DATE_FORMAT),
            ]
        )->fetchAllAssociative();

        foreach ($rows as $row) {
            yield $this->mapDatabaseRowToEventOccurrence($row);
        }
    }

    /**
     * @return iterable<int,EventOccurrence>
     */
    public function findEventOccurrencesByEventId(NodeAggregateIdentifier $eventId): iterable
    {
        /** @var array<int,DatabaseRow> $rows */
        $rows = $this->databaseConnection->executeQuery(
            'SELECT * FROM ' . self::TABLE_NAME
            . ' WHERE event_id = :eventId',
            [
                'eventId' => (string)$eventId,
            ]
        )->fetchAllAssociative();

        foreach ($rows as $row) {
            yield $this->mapDatabaseRowToEventOccurrence($row);
        }
    }

    /**
     * @return iterable<int,EventOccurrence>
     */
    public function findFutureEventOccurrencesByEventId(NodeAggregateIdentifier $eventId, \DateTimeImmutable $referenceDate): iterable
    {
        /** @var array<int,DatabaseRow> $rows */
        $rows = $this->databaseConnection->executeQuery(
            'SELECT * FROM ' . self::TABLE_NAME
            . ' WHERE event_id = :eventId AND start_date > :referenceDate',
            [
                'eventId' => (string)$eventId,
                'referenceDate' => $referenceDate->format(self::DATE_FORMAT),
            ]
        )->fetchAllAssociative();

        foreach ($rows as $row) {
            yield $this->mapDatabaseRowToEventOccurrence($row);
        }
    }

    public function removeAllOccurrencesByEventId(NodeAggregateIdentifier $eventId): void
    {
        $this->databaseConnection->executeStatement(
            'DELETE FROM ' . self::TABLE_NAME . ' WHERE event_id = :eventId',
            [
                'eventId' => (string)$eventId,
            ]
        );
    }

    public function removeAllFutureRecurrencesByEventId(NodeAggregateIdentifier $eventId, \DateTimeImmutable $referenceDate): void
    {
        $this->databaseConnection->executeStatement(
            'DELETE FROM ' . self::TABLE_NAME . ' WHERE event_id = :eventId AND end_date > :referenceDate AND source = :source',
            [
                'eventId' => (string)$eventId,
                'referenceDate' => $referenceDate->format(self::DATE_FORMAT),
                'source' => EventOccurrenceSource::SOURCE_RECURRENCE_RULE->value,
            ]
        );
    }

    public function replaceAllFutureRecurrencesByEventId(
        NodeAggregateIdentifier $calendarId,
        NodeAggregateIdentifier $eventId,
        RecurrenceRule $recurrenceRule,
        \DateTimeImmutable $referenceDate
    ): void {
        $renderer = new ArrayTransformer();
        /** @var list<EventOccurrence> $futureDates */
        $futureDates = [];

        $rule = new Rule($recurrenceRule->value);
        foreach (
            $renderer->transform(
                $rule,
                /** @todo make configurable */
                $rule->getEndDate() ? null : new BeforeConstraint($referenceDate->add(new \DateInterval('P1Y')))
            ) as $recurrence
        ) {
            $occurrence = EventOccurrence::tryFromRecurrence($eventId, $recurrence);
            if (!$occurrence instanceof EventOccurrence || $occurrence->startDate < $referenceDate) {
                continue;
            }

            $futureDates[] = $occurrence;
        }

        $this->databaseConnection->transactional(function () use ($calendarId, $eventId, $referenceDate, $futureDates) {
            $this->removeAllFutureRecurrencesByEventId($eventId, $referenceDate);

            foreach ($futureDates as $futureDate) {
                $this->databaseConnection->insert(
                    self::TABLE_NAME,
                    [
                        'calendar_id' => (string)$calendarId,
                        'event_id' => (string)$eventId,
                        'start_date' => $futureDate->startDate->format(self::DATE_FORMAT),
                        'end_date' => $futureDate->startDate->format(self::DATE_FORMAT),
                        'source' => EventOccurrenceSource::SOURCE_RECURRENCE_RULE->value,
                    ]
                );
            }
        });
    }

    /**
     * @param DatabaseRow $row
     */
    private function mapDatabaseRowToEventOccurrence(array $row): EventOccurrence
    {
        return new EventOccurrence(
            NodeAggregateIdentifier::fromString($row['event_id']),
            self::createDate($row['start_date']),
            self::createDate($row['end_date']),
        );
    }

    private static function createDate(string $dateString): \DateTimeImmutable
    {
        /** @var \DateTimeImmutable $result */
        $result = \DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $dateString);

        return $result;
    }
}
