<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Infrastructure;

use Neos\ContentRepository\Domain\Model\Node;
use Neos\Flow\Annotations as Flow;
use Sitegeist\GroundhogDay\Domain\EventDateZookeeper;
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
        private readonly EventDateZookeeper $eventDateZookeeper,
    ) {
    }

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
                $this->eventDateZookeeper->whenRecurrenceRuleWasChanged(new RecurrenceRuleWasChanged(
                    $node->getNodeAggregateIdentifier(),
                    $newValue
                ));
            }
        }
    }
}
