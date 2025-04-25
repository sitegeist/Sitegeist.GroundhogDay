<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain;

use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\Flow\Annotations as Flow;
use Recurr\Recurrence;

#[Flow\Proxy(false)]
final readonly class EventOccurrence
{
    public function __construct(
        public NodeAggregateIdentifier $eventId,
        public \DateTimeImmutable $startDate,
        public \DateTimeImmutable $endDate,
    ) {
    }

    public static function create(
        NodeAggregateIdentifier $eventId,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
    ): self {
        return new self($eventId, $startDate, $endDate);
    }

    public static function tryFromRecurrence(NodeAggregateIdentifier $eventId, Recurrence $recurrence): ?self
    {
        $startDate = $recurrence->getStart();
        if ($startDate instanceof \DateTime) {
            $startDate = \DateTimeImmutable::createFromMutable($startDate);
        }
        if (!$startDate instanceof \DateTimeImmutable) {
            return null;
        }

        $endDate = $recurrence->getEnd();
        if ($endDate instanceof \DateTime) {
            $endDate = \DateTimeImmutable::createFromMutable($endDate);
        }
        if (!$endDate instanceof \DateTimeImmutable) {
            return null;
        }

        return new self($eventId, $startDate, $endDate);
    }
}
