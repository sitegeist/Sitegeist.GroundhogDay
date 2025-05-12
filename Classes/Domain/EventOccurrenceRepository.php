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
use Sitegeist\GroundhogDay\Infrastructure\CalendarIsMissing;
use Sitegeist\GroundhogDay\Infrastructure\LocationIsMissing;

/**
 * @phpstan-type DatabaseRow array{
 *     calendar_id: string,
 *     event_id: string,
 *     start_date: string,
 *     end_date: string,
 *     start_date_utc: string,
 *     end_date_utc: string
 * }
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
    public function findEventAbsoluteOccurrencesWithinPeriod(
        NodeAggregateIdentifier $calendarId,
        DateTimeSpecification $startDate,
        DateTimeSpecification $endDate,
        \DateTimeZone $timeZone,
    ): iterable {
        $utcStartDate = $startDate->toDateTime($timeZone)->setTimezone(new \DateTimeZone('UTC'));
        $utcEndDate = $endDate->toDateTime($timeZone)->setTimezone(new \DateTimeZone('UTC'));
        /** @var array<int,DatabaseRow> $rows */
        $rows = $this->databaseConnection->executeQuery(
            'SELECT * FROM ' . self::TABLE_NAME
             . ' WHERE calendar_id = :calendarId AND start_date_utc <= :endDate AND end_date_utc >= :startDate',
            [
                'calendarId' => (string)$calendarId,
                'startDate' => $utcStartDate->format(self::DATE_FORMAT),
                'endDate' => $utcEndDate->format(self::DATE_FORMAT),
            ]
        )->fetchAllAssociative();

        foreach ($rows as $row) {
            yield $this->mapDatabaseRowToEventAbsoluteOccurrence($row, $timeZone);
        }
    }

    /**
     * @return iterable<int,EventOccurrence>
     */
    public function findEventLocalOccurrencesWithinPeriod(
        NodeAggregateIdentifier $calendarId,
        DateTimeSpecification $startDate,
        DateTimeSpecification $endDate,
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
            yield $this->mapDatabaseRowToEventLocalOccurrence($row);
        }
    }

    /**
     * @return iterable<int,EventOccurrence>
     */
    public function findEventAbsoluteOccurrencesByEventId(
        NodeAggregateIdentifier $eventId,
        \DateTimeZone $timeZone,
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
            yield $this->mapDatabaseRowToEventAbsoluteOccurrence($row, $timeZone);
        }
    }

    /**
     * @return iterable<int,EventOccurrence>
     */
    public function findEventLocalOccurrencesByEventId(
        NodeAggregateIdentifier $eventId,
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
            yield $this->mapDatabaseRowToEventLocalOccurrence($row);
        }
    }

    /**
     * @return iterable<int,EventOccurrence>
     */
    public function findFutureEventAbsoluteOccurrencesByEventId(
        NodeAggregateIdentifier $eventId,
        DateTimeSpecification $referenceDate,
        \DateTimeZone $timeZone,
    ): iterable {
        $utcReferenceDate = $referenceDate->toDateTime($timeZone)->setTimezone(new \DateTimeZone('UTC'));
        /** @var array<int,DatabaseRow> $rows */
        $rows = $this->databaseConnection->executeQuery(
            'SELECT * FROM ' . self::TABLE_NAME
            . ' WHERE event_id = :eventId AND start_date_utc > :referenceDate',
            [
                'eventId' => (string)$eventId,
                'referenceDate' => $utcReferenceDate->format(self::DATE_FORMAT),
            ]
        )->fetchAllAssociative();

        foreach ($rows as $row) {
            yield $this->mapDatabaseRowToEventAbsoluteOccurrence($row, $timeZone);
        }
    }

    /**
     * @return iterable<int,EventOccurrence>
     */
    public function findFutureEventLocalOccurrencesByEventId(
        NodeAggregateIdentifier $eventId,
        DateTimeSpecification $referenceDate,
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
            yield $this->mapDatabaseRowToEventLocalOccurrence($row);
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

    public function removeAllFutureOccurrencesByEventId(NodeAggregateIdentifier $eventId, DateTimeSpecification $referenceDate): void
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
        \DateTimeZone $locationTimezone,
    ): void {
        $dates = $occurrenceSpecification->resolveDates(null, null, $locationTimezone);

        $this->databaseConnection->transactional(function () use ($calendarId, $eventId, $dates) {
            foreach ($dates as $date) {
                $this->databaseConnection->insert(
                    self::TABLE_NAME,
                    [
                        'calendar_id' => (string)$calendarId,
                        'event_id' => (string)$eventId,
                        'start_date' => $date->startDate->format(self::DATE_FORMAT),
                        'end_date' => $date->endDate->format(self::DATE_FORMAT),
                        'start_date_utc' => $date->startDate->setTimezone(new \DateTimeZone('UTC'))->format(self::DATE_FORMAT),
                        'end_date_utc' => $date->endDate->setTimezone(new \DateTimeZone('UTC'))->format(self::DATE_FORMAT),
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
        \DateTimeZone $locationTimeZone,
    ): void {
        $this->databaseConnection->transactional(function () use ($eventId, $calendarId, $occurrenceSpecification, $referenceDate, $locationTimeZone) {
            $this->removeAllFutureOccurrencesByEventId($eventId, DateTimeSpecification::fromDateTimeIgnoringTimeZone($referenceDate->setTimezone($locationTimeZone)));

            foreach ($occurrenceSpecification->resolveDates($referenceDate, null, $locationTimeZone) as $futureDate) {
                $this->databaseConnection->insert(
                    self::TABLE_NAME,
                    [
                        'calendar_id' => (string)$calendarId,
                        'event_id' => (string)$eventId,
                        'start_date' => $futureDate->startDate->format(self::DATE_FORMAT),
                        'end_date' => $futureDate->endDate->format(self::DATE_FORMAT),
                        'start_date_utc' => $futureDate->startDate->setTimezone(new \DateTimeZone('UTC'))->format(self::DATE_FORMAT),
                        'end_date_utc' => $futureDate->endDate->setTimezone(new \DateTimeZone('UTC'))->format(self::DATE_FORMAT),
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
                $calendarId = $this->resolveCalendarId($nodeDataRecord);
                $locationTimezone = $this->resolveLocationTimezone($nodeDataRecord);
                foreach ($occurrenceSpecification->resolveRecurrenceDates($referenceDate, null, $locationTimezone) as $date) {
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
                                    'start_date_utc' => $date->startDate->setTimezone(new \DateTimeZone('UTC'))->format(self::DATE_FORMAT),
                                    'end_date_utc' => $date->endDate->setTimezone(new \DateTimeZone('UTC'))->format(self::DATE_FORMAT),
                                ]
                            );
                        }
                    });
                }
            }
        }
    }

    /**
     * To be replaced by findParentNodeAggregates in Neos 9
     * We assume that events are varied and moved on aggregate level relative to their calendar and location here
     */
    private function resolveCalendarId(NodeData $eventNodeData): NodeAggregateIdentifier
    {
        $calendarCandidate = $eventNodeData;
        while ($calendarCandidate) {
            if ($calendarCandidate->getNodeType()->isOfType('Sitegeist.GroundhogDay:Mixin.Calendar')) {
                return NodeAggregateIdentifier::fromString($calendarCandidate->getIdentifier());
            }
            $calendarCandidate = $this->nodeDataRepository->findOneByPath($calendarCandidate->getParentPath(), $eventNodeData->getWorkspace());
        }
        throw CalendarIsMissing::butWasRequired(NodeAggregateIdentifier::fromString($eventNodeData->getIdentifier()));
    }

    /**
     * To be replaced by findParentNodeAggregates in Neos 9
     * We assume that events are varied and moved on aggregate level here
     */
    private function resolveLocationTimezone(NodeData $eventNodeData): \DateTimeZone
    {
        $locationCandidate = $eventNodeData;
        while ($locationCandidate) {
            if ($locationCandidate->getNodeType()->isOfType('Sitegeist.GroundhogDay:Mixin.Location')) {
                return $locationCandidate->getProperty('timeZone') ?: new \DateTimeZone('UTC');
            }
            $locationCandidate = $this->nodeDataRepository->findOneByPath($locationCandidate->getParentPath(), $eventNodeData->getWorkspace());
        }
        throw LocationIsMissing::butWasRequired(NodeAggregateIdentifier::fromString($eventNodeData->getIdentifier()));
    }

    /**
     * @param DatabaseRow $row
     */
    private function mapDatabaseRowToEventAbsoluteOccurrence(array $row, \DateTimeZone $timeZone): EventOccurrence
    {
        return new EventOccurrence(
            NodeAggregateIdentifier::fromString($row['event_id']),
            self::createAbsoluteDateSpecification($row['start_date_utc'], $timeZone),
            self::createAbsoluteDateSpecification($row['end_date_utc'], $timeZone),
        );
    }

    /**
     * @param DatabaseRow $row
     */
    private function mapDatabaseRowToEventLocalOccurrence(array $row): EventOccurrence
    {
        return new EventOccurrence(
            NodeAggregateIdentifier::fromString($row['event_id']),
            self::createLocalDateSpecification($row['start_date']),
            self::createLocalDateSpecification($row['end_date']),
        );
    }

    private static function createLocalDateSpecification(string $dateString): DateTimeSpecification
    {
        $dateTime = \DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $dateString, new \DateTimeZone('UTC'));
        assert($dateTime instanceof \DateTimeImmutable);

        return DateTimeSpecification::fromDateTimeIgnoringTimeZone($dateTime);
    }

    private static function createAbsoluteDateSpecification(string $dateString, \DateTimeZone $timeZone): DateTimeSpecification
    {
        $dateTime = \DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $dateString, new \DateTimeZone('UTC'));
        assert($dateTime instanceof \DateTimeImmutable);

        return DateTimeSpecification::fromDateTimeIgnoringTimeZone($dateTime->setTimezone($timeZone));
    }
}
