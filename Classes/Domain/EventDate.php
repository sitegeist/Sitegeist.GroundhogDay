<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain;

use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final readonly class EventDate
{
    public function __construct(
        public NodeAggregateIdentifier $eventId,
        public \DateTimeImmutable $date,
        public int $dayOfEvent,
    ) {
    }

    public static function create(
        NodeAggregateIdentifier $eventId,
        \DateTimeImmutable $date,
        int $dayOfEvent,
    ) {
        return new self($eventId, $date, $dayOfEvent);
    }
}
