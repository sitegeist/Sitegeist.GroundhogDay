<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain\OccurrenceHandling;

use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\Flow\Annotations as Flow;
use Sitegeist\GroundhogDay\Domain\EventOccurrenceSpecification;

/**
 * The event describing that the occurrence specification of an event was changed.
 */
#[Flow\Proxy(false)]
final readonly class EventOccurrenceSpecificationWasChanged
{
    private function __construct(
        public NodeAggregateIdentifier $eventId,
        public NodeAggregateIdentifier $calendarId,
        public EventOccurrenceSpecification $occurrenceSpecification,
        public \DateTimeImmutable $dateOfChange,
        public \DateTimeZone $locationTimezone,
    ) {
    }

    public static function create(
        NodeAggregateIdentifier $eventId,
        NodeAggregateIdentifier $calendarId,
        EventOccurrenceSpecification $occurrenceSpecification,
        \DateTimeImmutable $dateOfChange,
        \DateTimeZone $locationTimezone,
    ): self {
        return new self(
            eventId: $eventId,
            calendarId: $calendarId,
            occurrenceSpecification: $occurrenceSpecification,
            dateOfChange: $dateOfChange->setTimezone(new \DateTimeZone('UTC')),
            locationTimezone: $locationTimezone,
        );
    }
}
