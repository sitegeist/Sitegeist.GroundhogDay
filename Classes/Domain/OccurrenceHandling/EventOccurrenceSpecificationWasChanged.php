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
    public function __construct(
        public NodeAggregateIdentifier $eventId,
        public NodeAggregateIdentifier $calendarId,
        public EventOccurrenceSpecification $occurrenceSpecification,
        public \DateTimeImmutable $dateOfChange,
    ) {
    }

    public static function create(
        NodeAggregateIdentifier $eventId,
        NodeAggregateIdentifier $calendarId,
        EventOccurrenceSpecification $occurrenceSpecification,
        \DateTimeImmutable $dateOfChange,
    ): self {
        return new self($eventId, $calendarId, $occurrenceSpecification, $dateOfChange);
    }
}
