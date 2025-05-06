<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain;

use Neos\Flow\Annotations as Flow;
use Recurr\Recurrence;

#[Flow\Proxy(false)]
final readonly class EventDates
{
    public function __construct(
        public \DateTimeImmutable $startDate,
        public \DateTimeImmutable $endDate,
    ) {
    }

    public static function create(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
    ): self {
        return new self($startDate, $endDate);
    }

    public static function tryFromRecurrence(Recurrence $recurrence): ?self
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

        return new self($startDate, $endDate);
    }
}
