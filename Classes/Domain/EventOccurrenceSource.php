<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain;

enum EventOccurrenceSource: string
{
    case SOURCE_RECURRENCE_RULE = 'recurrenceRule';
    case SOURCE_MANUAL = 'manual';
}
