<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Infrastructure;

use Neos\ContentRepository\Domain\Model\Node;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\Repository\NodeDataRepository;
use Neos\ContentRepository\Exception\NodeException;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Domain\Service\ContentContextFactory;
use Sitegeist\GroundhogDay\Domain\EventOccurrenceSpecification;
use Sitegeist\GroundhogDay\Domain\OccurrenceHandling\EventOccurrenceZookeeper;
use Sitegeist\GroundhogDay\Domain\OccurrenceHandling\EventWasCreated;
use Sitegeist\GroundhogDay\Domain\OccurrenceHandling\EventWasRemoved;
use Sitegeist\GroundhogDay\Domain\OccurrenceHandling\EventOccurrenceSpecificationWasChanged;

/**
 * The event relay infrastructure service
 *
 * Translates CR / Neos events into GroundhogDay domain events
 */
#[Flow\Scope('singleton')]
class EventRelay
{
    /**
     * @param array<string,string> $eventIdsToCheckForRemoval
     * @param array<EventOccurrenceSpecificationWasChanged|EventWasCreated> $eventsToPublish
     */
    public function __construct(
        private readonly EventOccurrenceZookeeper $eventOccurrenceZookeeper,
        private readonly NodeDataRepository $nodeDataRepository,
        private readonly ContentContextFactory $contentContextFactory,
        private array $eventIdsToCheckForRemoval = [],
        private array $eventsToPublish = [],
    ) {
    }

    public function beforeNodeWasPublished(Node $node, Workspace $targetWorkspace): void
    {
        if (
            $targetWorkspace->getName() === 'live'
            && $node->getNodeType()->isOfType('Sitegeist.GroundhogDay:Mixin.Event')
            && !$node->isRemoved() // removal is handled in ::afterNodeWasPublished
        ) {
            $newValue = $node->getProperty('occurrence');
            if (!$newValue instanceof EventOccurrenceSpecification) {
                return;
            }

            $liveContextProperties = $node->getContext()->getProperties();
            $liveContextProperties['workspaceName'] = 'live';
            $liveContext = $this->contentContextFactory->create($liveContextProperties);
            $liveEvent = $liveContext->getNodeByIdentifier($node->getIdentifier());
            if ($liveEvent) {
                $oldValue = $liveEvent->getProperty('occurrence');
                if (
                    !$oldValue instanceof EventOccurrenceSpecification
                    || !$newValue->equals($oldValue)
                ) {
                    $this->eventsToPublish[] = EventOccurrenceSpecificationWasChanged::create(
                        $node->getNodeAggregateIdentifier(),
                        $this->resolveCalendarId($node),
                        $newValue,
                        new \DateTimeImmutable(),
                    );
                }
            } else {
                $this->eventsToPublish[] = EventWasCreated::create(
                    $node->getNodeAggregateIdentifier(),
                    $this->resolveCalendarId($node),
                    $newValue,
                );
            }
        }
    }

    public function afterNodeWasPublished(Node $node, Workspace $targetWorkspace): void
    {
        if (
            $targetWorkspace->getName() === 'live'
            && $node->getNodeType()->isOfType('Sitegeist.GroundhogDay:Mixin.Event')
            && $node->isRemoved() // creation and modification is handled in ::beforeNodeWasPublished
        ) {
            $this->eventIdsToCheckForRemoval[$node->getIdentifier()] = $node->getIdentifier();
        }
    }

    /**
     * @throws CalendarIsMissing
     */
    private function resolveCalendarId(Node $event): NodeAggregateIdentifier
    {
        $calendarCandidate = $event;
        while ($calendarCandidate) {
            if ($calendarCandidate->getNodeType()->isOfType('Sitegeist.GroundhogDay:Mixin.Calendar')) {
                return $calendarCandidate->getNodeAggregateIdentifier();
            }
            try {
                $calendarCandidate = $calendarCandidate->findParentNode();
            } catch (NodeException) {
                throw CalendarIsMissing::butWasRequired($event->getNodeAggregateIdentifier());
            }
        }
    }

    public function shutdownObject(): void
    {
        foreach ($this->eventIdsToCheckForRemoval as $eventId) {
            $recordQuery = $this->nodeDataRepository->createQuery();
            $numberOfRemainingRecords = $recordQuery->matching(
                /** @phpstan-ignore-next-line (botched variadics) */
                $recordQuery->logicalAnd(
                    $recordQuery->equals('workspace', 'live'),
                    $recordQuery->equals('identifier', $eventId),
                    $recordQuery->equals('removed', false),
                )
            )->count();

            # We only ever remove event occurrences once the last remaining variant of the calendar event was removed
            if ($numberOfRemainingRecords === 0) {
                $this->eventOccurrenceZookeeper->whenEventWasRemoved(new EventWasRemoved(
                    NodeAggregateIdentifier::fromString($eventId),
                ));
            }
        }

        foreach ($this->eventsToPublish as $eventToPublish) {
            switch (get_class($eventToPublish)) {
                case EventWasCreated::class:
                    $this->eventOccurrenceZookeeper->whenEventWasCreated($eventToPublish);
                    break;
                case EventOccurrenceSpecificationWasChanged::class:
                    $this->eventOccurrenceZookeeper->whenEventOccurrenceSpecificationWasChanged($eventToPublish);
                    break;
            }
        }
    }
}
