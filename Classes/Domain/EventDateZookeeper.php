<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain;

use Neos\Flow\Annotations as Flow;

/**
 * The event date zookeeper, implementing the policy that whenever
 * * an event's recurrence rule was changed
 * * time has passed
 * the event dates are to be updated
 */
#[Flow\Scope('singleton')]
final class EventDateZookeeper
{
    public function whenRecurrenceRuleWasChanged(RecurrenceRuleWasChanged $event): void
    {
        #\Neos\Flow\var_dump($event);
        #exit();
    }

    public function whenTimeHasPassed(TimeHasPassed $event): void
    {
        #\Neos\Flow\var_dump($event);
        #exit();
    }
}
