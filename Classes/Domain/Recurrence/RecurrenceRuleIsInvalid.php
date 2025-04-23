<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain\Recurrence;

use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final class RecurrenceRuleIsInvalid extends \DomainException
{
    public static function butWasTriedToBeUsedAsSuch(string $attemptedValue): self
    {
        return new self('Value ' . $attemptedValue . ' is no valid recurrence rule.', 1745412636);
    }
}
