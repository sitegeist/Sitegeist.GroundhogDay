<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain\Recurrence;

use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\Flow\Annotations as Flow;

/**
 * The event describing that the recurrence dates of an event were changed.
 */
#[Flow\Proxy(false)]
final readonly class RecurrenceDatesWereChanged
{
    public function __construct(
        public NodeAggregateIdentifier $calendarId,
        public NodeAggregateIdentifier $eventId,
        public \DateTimeImmutable $startDate,
        public ?\DateTimeImmutable $endDate,
        public ?RecurrenceDates $changedDates,
        public \DateTimeImmutable $dateOfChange,
    ) {
    }
}
