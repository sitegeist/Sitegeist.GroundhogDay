<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Infrastructure;

use Neos\ContentRepository\Domain\Model\Node;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Exception\NodeException;
use Neos\Flow\Annotations as Flow;
use Sitegeist\GroundhogDay\Domain\EventOccurrenceZookeeper;
use Sitegeist\GroundhogDay\Domain\Recurrence\RecurrenceRule;
use Sitegeist\GroundhogDay\Domain\Recurrence\RecurrenceRuleWasChanged;

/**
 * The event relay infrastructure service
 *
 * Translates CR / Neos events into GroundhogDay domain events
 */
#[Flow\Scope('singleton')]
class EventRelay
{
    public function __construct(
        private readonly EventOccurrenceZookeeper $eventOccurrenceZookeeper,
    ) {
    }

    /**
     * @throws CalendarIsMissing
     */
    public function registerPropertyChange(Node $node, string $propertyName, mixed $oldValue, mixed $newValue): void
    {
        if (
            $node->getWorkspace()->getName() === 'live'
            && $node->getNodeType()->isOfType('Sitegeist.GroundhogDay:Mixin.Event')
            && $propertyName === 'recurrenceRule'
        ) {
            $oldComparisonValue = $oldValue instanceof RecurrenceRule ? $oldValue->toString() : null;
            $newComparisonValue = $newValue instanceof RecurrenceRule ? $newValue->toString() : null;

            if ($oldComparisonValue !== $newComparisonValue) {
                $this->eventOccurrenceZookeeper->whenRecurrenceRuleWasChanged(new RecurrenceRuleWasChanged(
                    $this->resolveCalendarId($node),
                    $node->getNodeAggregateIdentifier(),
                    $newValue,
                    new \DateTimeImmutable(),
                ));
            }
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
}
