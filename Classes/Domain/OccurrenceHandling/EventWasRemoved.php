<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain\OccurrenceHandling;

use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\Flow\Annotations as Flow;

/**
 * The event describing that a (calendar) event was removed
 */
#[Flow\Proxy(false)]
final readonly class EventWasRemoved
{
    public function __construct(
        public NodeAggregateIdentifier $eventId,
    ) {
    }
}
