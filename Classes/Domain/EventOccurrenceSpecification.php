<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain;

use Neos\Flow\Annotations as Flow;
use Sitegeist\GroundhogDay\Domain\Recurrence\RecurrenceDates;
use Sitegeist\GroundhogDay\Domain\Recurrence\RecurrenceRule;

#[Flow\Proxy(false)]
final readonly class EventOccurrenceSpecification
{
    public function __construct(
        public \DateTimeImmutable $startDate,
        public ?\DateTimeImmutable $endDate,
        public ?RecurrenceRule $recurrenceRule,
        public ?RecurrenceDates $recurrenceDates,
    ) {
    }

    public function equalsForRecurrence(self $other): bool
    {
        return $this->recurrenceRule === null && $other->recurrenceRule === null ||
            $this->startDate == $other->startDate
            && $this->endDate == $other->endDate
            && $this->recurrenceRule?->value === $other->recurrenceRule?->value;
    }
}
