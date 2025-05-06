<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain;

use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\Flow\Annotations as Flow;

/**
 * The event describing that a (calendar) event was created
 */
#[Flow\Proxy(false)]
final readonly class EventWasCreated
{
    public function __construct(
        public NodeAggregateIdentifier $eventId,
        public NodeAggregateIdentifier $calendarId,
        public EventOccurrenceSpecification $occurrenceSpecification,
    ) {
    }

    public static function create(
        NodeAggregateIdentifier $eventId,
        NodeAggregateIdentifier $calendarId,
        EventOccurrenceSpecification $occurrenceSpecification,
    ): self {
        return new self($eventId, $calendarId, $occurrenceSpecification);
    }
}
