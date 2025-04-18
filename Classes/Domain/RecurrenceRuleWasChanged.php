<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain;

use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\Flow\Annotations as Flow;
use Recurr\Rule;

/**
 * The event describing that the recurrence rule of an event was changed.
 */
#[Flow\Proxy(false)]
final readonly class RecurrenceRuleWasChanged
{
    public function __construct(
        public NodeAggregateIdentifier $eventId,
        public ?Rule $changedRule,
    ) {
    }
}
