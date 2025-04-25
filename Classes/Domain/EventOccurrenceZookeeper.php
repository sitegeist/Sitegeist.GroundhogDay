<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain;

use Neos\Flow\Annotations as Flow;
use Sitegeist\GroundhogDay\Domain\Recurrence\RecurrenceRuleWasChanged;

/**
 * The event date zookeeper, implementing the policy that whenever
 * * an event's recurrence rule was changed
 * * time has passed
 * the event dates are to be updated
 */
#[Flow\Scope('singleton')]
final class EventOccurrenceZookeeper
{
    public function __construct(
        private readonly EventOccurrenceRepository $eventDateRepository,
    ) {
    }

    public function whenRecurrenceRuleWasChanged(RecurrenceRuleWasChanged $event): void
    {
        if ($event->changedRule === null) {
            $this->eventDateRepository->removeAllFutureRecurrencesByEventId($event->eventId, $event->dateOfChange);
        } else {
            $this->eventDateRepository->replaceAllFutureRecurrencesByEventId($event->eventId, $event->changedRule, $event->dateOfChange);
        }
    }

    public function whenTimeHasPassed(TimeHasPassed $event): void
    {
        #\Neos\Flow\var_dump($event);
        #exit();
    }
}
