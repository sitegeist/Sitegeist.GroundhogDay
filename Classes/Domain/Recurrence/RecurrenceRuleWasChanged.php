<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain\Recurrence;

use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\Flow\Annotations as Flow;

/**
 * The event describing that the recurrence rule of an event was changed.
 */
#[Flow\Proxy(false)]
final readonly class RecurrenceRuleWasChanged
{
    public function __construct(
        public NodeAggregateIdentifier $calendarId,
        public NodeAggregateIdentifier $eventId,
        public ?RecurrenceRule $changedRule,
        public \DateTimeImmutable $startDate,
        public ?\DateTimeImmutable $endDate,
        public \DateTimeImmutable $dateOfChange,
    ) {
    }
}
