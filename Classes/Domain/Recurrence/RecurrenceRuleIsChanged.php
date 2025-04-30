<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain\Recurrence;

use Neos\Flow\Annotations as Flow;
use Sitegeist\GroundhogDay\Domain\EventOccurrenceSpecification;

#[Flow\Proxy(false)]
final readonly class RecurrenceRuleIsChanged
{
    public static function isSatisfiedByEventOccurrenceSpecifications(
        ?EventOccurrenceSpecification $oldSpecification,
        EventOccurrenceSpecification $newSpecification
    ): bool {
        if ($oldSpecification === null) {
            return $newSpecification->recurrenceRule !== null;
        }

        return !$oldSpecification->equalsForRecurrence($newSpecification);
    }
}
