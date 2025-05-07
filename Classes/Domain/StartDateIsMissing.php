<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain;

final class StartDateIsMissing extends \InvalidArgumentException
{
    public static function butWasRequired(string $attemptedValue): self
    {
        return new self('Start date is missing in value ' . $attemptedValue
            . ', must contain a DTSTART: line',
            1746602782
        );
    }
}
