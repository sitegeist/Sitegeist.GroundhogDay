<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain;

use Doctrine\DBAL\Connection;
use Neos\ContentRepository\Domain\Model\NodeData;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\Repository\NodeDataRepository;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Doctrine\ConnectionFactory;

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
        private readonly NodeDataRepository $nodeDataRepository,
        private readonly NodeTypeManager $nodeTypeManager,
    ) {
        $this->databaseConnection = $connectionFactory->create();
    }

    /**
     * @return iterable<int,EventOccurrence>
     */
    public function findEventOccurrencesWithinPeriod(
        NodeAggregateIdentifier $calendarId,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        ?\DateTimeZone $timeZone = null
    ): iterable {
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
            yield $this->mapDatabaseRowToEventOccurrence($row, $timeZone);
        }
    }

    /**
     * @return iterable<int,EventOccurrence>
     */
    public function findEventOccurrencesByEventId(
        NodeAggregateIdentifier $eventId,
        ?\DateTimeZone $timeZone = null,
    ): iterable {
        /** @var array<int,DatabaseRow> $rows */
        $rows = $this->databaseConnection->executeQuery(
            'SELECT * FROM ' . self::TABLE_NAME
            . ' WHERE event_id = :eventId',
            [
                'eventId' => (string)$eventId,
            ]
        )->fetchAllAssociative();

        foreach ($rows as $row) {
            yield $this->mapDatabaseRowToEventOccurrence($row, $timeZone);
        }
    }

    /**
     * @return iterable<int,EventOccurrence>
     */
    public function findFutureEventOccurrencesByEventId(
        NodeAggregateIdentifier $eventId,
        \DateTimeImmutable $referenceDate,
        ?\DateTimeZone $timeZone = null,
    ): iterable {
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
            yield $this->mapDatabaseRowToEventOccurrence($row, $timeZone);
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

    public function removeAllFutureOccurrencesByEventId(NodeAggregateIdentifier $eventId, \DateTimeImmutable $referenceDate): void
    {
        $this->databaseConnection->executeStatement(
            'DELETE FROM ' . self::TABLE_NAME . ' WHERE event_id = :eventId AND end_date > :referenceDate',
            [
                'eventId' => (string)$eventId,
                'referenceDate' => $referenceDate->format(self::DATE_FORMAT),
            ]
        );
    }

    public function initializeOccurrences(
        NodeAggregateIdentifier $eventId,
        NodeAggregateIdentifier $calendarId,
        EventOccurrenceSpecification $occurrenceSpecification,
    ): void {
        $dates = $occurrenceSpecification->resolveDates();

        $this->databaseConnection->transactional(function () use ($calendarId, $eventId, $dates) {
            foreach ($dates as $date) {
                $this->databaseConnection->insert(
                    self::TABLE_NAME,
                    [
                        'calendar_id' => (string)$calendarId,
                        'event_id' => (string)$eventId,
                        'start_date' => $date->startDate->format(self::DATE_FORMAT),
                        'end_date' => $date->endDate->format(self::DATE_FORMAT),
                    ]
                );
            }
        });
    }

    public function replaceAllFutureOccurrencesByEventId(
        NodeAggregateIdentifier $eventId,
        NodeAggregateIdentifier $calendarId,
        EventOccurrenceSpecification $occurrenceSpecification,
        \DateTimeImmutable $referenceDate,
    ): void {
        $this->databaseConnection->transactional(function () use ($eventId, $calendarId, $occurrenceSpecification, $referenceDate) {
            $this->removeAllFutureOccurrencesByEventId($eventId, $referenceDate);

            foreach ($occurrenceSpecification->resolveDates($referenceDate) as $futureDate) {
                $this->databaseConnection->insert(
                    self::TABLE_NAME,
                    [
                        'calendar_id' => (string)$calendarId,
                        'event_id' => (string)$eventId,
                        'start_date' => $futureDate->startDate->format(self::DATE_FORMAT),
                        'end_date' => $futureDate->endDate->format(self::DATE_FORMAT),
                    ]
                );
            }
        });
    }

    public function continueAllRecurrenceRules(\DateTimeImmutable $referenceDate): void
    {
        $recordQuery = $this->nodeDataRepository->createQuery();
        $nodeDataRecords = $recordQuery->matching(
            /** @phpstan-ignore-next-line (botched variadics) */
            $recordQuery->logicalAnd(
                $recordQuery->equals('workspace', 'live'),
                $recordQuery->in(
                    'nodeType',
                    array_map(
                        fn (NodeType $nodeType): string => $nodeType->getName(),
                        $this->nodeTypeManager->getSubNodeTypes('Sitegeist.GroundhogDay:Mixin.Event', false)
                    )
                )
            )
        )->execute();

        /** @var iterable<NodeData> $nodeDataRecords */
        foreach ($nodeDataRecords as $nodeDataRecord) {
            $occurrenceSpecification = $nodeDataRecord->getProperty('occurrence');
            if ($occurrenceSpecification instanceof EventOccurrenceSpecification && $occurrenceSpecification->recurrenceRule !== null) {
                $eventId = $nodeDataRecord->getIdentifier();
                $lastOccurrenceRecord = $this->findLastOccurrenceRecord(NodeAggregateIdentifier::fromString($eventId));
                if (!$lastOccurrenceRecord) {
                    // this is technically not correct, but resolving ancestors from event IDs is something for Neos 9
                    continue;
                }
                $calendarId = $lastOccurrenceRecord['calendar_id'];
                foreach ($occurrenceSpecification->resolveRecurrenceDates($referenceDate) as $date) {
                    $this->databaseConnection->transactional(function () use ($eventId, $calendarId, $date) {
                        $countRow = $this->databaseConnection->executeQuery(
                            'SELECT COUNT(*) FROM ' . self::TABLE_NAME . ' WHERE event_id = :eventId AND start_date = :startDate',
                            [
                                'eventId' => $eventId,
                                'startDate' => $date->startDate->format(self::DATE_FORMAT),
                            ]
                        )->fetchAssociative() ?: [];
                        $numberOfRecords = $countRow['COUNT(*)'] ?? 0;
                        if ($numberOfRecords === 0) {
                            $this->databaseConnection->insert(
                                self::TABLE_NAME,
                                [
                                    'calendar_id' => $calendarId,
                                    'event_id' => $eventId,
                                    'start_date' => $date->startDate->format(self::DATE_FORMAT),
                                    'end_date' => $date->endDate->format(self::DATE_FORMAT),
                                ]
                            );
                        }
                    });
                }
            }
        }
    }

    /**
     * @param DatabaseRow $row
     */
    private function mapDatabaseRowToEventOccurrence(array $row, ?\DateTimeZone $timeZone): EventOccurrence
    {
        return new EventOccurrence(
            NodeAggregateIdentifier::fromString($row['event_id']),
            self::createDate($row['start_date'], $timeZone),
            self::createDate($row['end_date'], $timeZone),
        );
    }

    /**
     * @return ?DatabaseRow
     */
    private function findLastOccurrenceRecord(NodeAggregateIdentifier $eventId): ?array
    {
        /** @phpstan-ignore-next-line (array shenanigans) */
        return $this->databaseConnection->executeQuery(
            'SELECT * FROM ' . self::TABLE_NAME
            . ' WHERE event_id = :eventId'
            . ' ORDER BY start_date DESC'
            . ' LIMIT 1',
            [
                'eventId' => (string)$eventId,
            ]
        )->fetchAssociative() ?: null;
    }

    private static function createDate(string $dateString, ?\DateTimeZone $timeZone): \DateTimeImmutable
    {
        /** @var \DateTimeImmutable $result */
        $result = \DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $dateString, $timeZone);

        return $result;
    }
}
