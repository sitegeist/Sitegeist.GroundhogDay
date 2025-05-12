<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Infrastructure;

use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;

final class LocationIsMissing extends \RuntimeException
{
    public static function butWasRequired(NodeAggregateIdentifier $eventId): self
    {
        return new self('Failed to resolve location time zone for event '
            . $eventId . ', one of its ancestors must be a location',
            1746795172
        );
    }
}
