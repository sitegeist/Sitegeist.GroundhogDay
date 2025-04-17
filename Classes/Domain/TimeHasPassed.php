<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain;

use Neos\Flow\Annotations as Flow;

/**
 * The event describing the passage of time
 *
 * To be emitted e.g. by command controllers via cronjob and to be consumed by the @see EventDateZookeeper
 */
#[Flow\Proxy(false)]
final readonly class TimeHasPassed
{
    public function __construct(
        public \DateTimeImmutable $dateTime,
    ) {
    }

    public static function create(\DateTimeImmutable $dateTime): self
    {
        return new self($dateTime);
    }
}
