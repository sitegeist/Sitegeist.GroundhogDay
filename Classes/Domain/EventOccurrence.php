<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain;

use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final readonly class EventOccurrence
{
    public function __construct(
        public NodeAggregateIdentifier $eventId,
        public DateTimeSpecification $startDate,
        public DateTimeSpecification $endDate,
    ) {
    }

    public static function create(
        NodeAggregateIdentifier $eventId,
        DateTimeSpecification $startDate,
        DateTimeSpecification $endDate,
    ): self {
        return new self($eventId, $startDate, $endDate);
    }
}
