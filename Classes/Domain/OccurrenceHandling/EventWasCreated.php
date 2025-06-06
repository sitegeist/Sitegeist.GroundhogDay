<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain\OccurrenceHandling;

use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\Flow\Annotations as Flow;
use Sitegeist\GroundhogDay\Domain\EventOccurrenceSpecification;

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
        public \DateTimeZone $locationTimezone,
    ) {
    }

    public static function create(
        NodeAggregateIdentifier $eventId,
        NodeAggregateIdentifier $calendarId,
        EventOccurrenceSpecification $occurrenceSpecification,
        \DateTimeZone $locationTimezone,
    ): self {
        return new self(
            eventId: $eventId,
            calendarId: $calendarId,
            occurrenceSpecification: $occurrenceSpecification,
            locationTimezone: $locationTimezone
        );
    }
}
