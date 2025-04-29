<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Infrastructure;

use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;

final class CalendarIsMissing extends \RuntimeException
{
    public static function butWasRequired(NodeAggregateIdentifier $eventId): self
    {
        return new self('Failed to resolve calendar ID for event '
            . $eventId . ', one of its ancestors must be a calendar',
            1745919357
        );
    }
}
