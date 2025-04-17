<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Command;

use Neos\Flow\Cli\CommandController;
use Sitegeist\GroundhogDay\Domain\EventDateZookeeper;
use Sitegeist\GroundhogDay\Domain\TimeHasPassed;

final class EventDateCommandController extends CommandController
{
    public function __construct(
        private readonly EventDateZookeeper $eventDateZookeeper,
    ) {
        parent::__construct();
    }

    public function updateCommand(): void
    {
        $this->eventDateZookeeper->whenTimeHasPassed(TimeHasPassed::create(new \DateTimeImmutable()));
    }
}
