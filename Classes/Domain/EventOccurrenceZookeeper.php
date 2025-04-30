<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain;

use Neos\Flow\Annotations as Flow;
use Sitegeist\GroundhogDay\Domain\Recurrence\RecurrenceDatesWereChanged;
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
        private readonly EventOccurrenceRepository $eventOccurrenceRepository,
    ) {
    }

    public function whenRecurrenceRuleWasChanged(RecurrenceRuleWasChanged $event): void
    {
        if ($event->changedRule === null) {
            $this->eventOccurrenceRepository->removeAllFutureRecurrencesByEventId($event->eventId, $event->dateOfChange);
        } else {
            $this->eventOccurrenceRepository->replaceAllFutureRecurrencesByEventId(
                $event->calendarId,
                $event->eventId,
                $event->changedRule,
                $event->startDate,
                $event->endDate,
                $event->dateOfChange
            );
        }
    }

    public function whenRecurrenceDatesWereChanged(RecurrenceDatesWereChanged $event): void
    {
        if ($event->changedDates === null) {
            $this->eventOccurrenceRepository->removeAllFutureManualOccurrencesByEventId($event->eventId, $event->dateOfChange);
        } else {
            $this->eventOccurrenceRepository->replaceAllFutureManualOccurrencesByEventId(
                $event->calendarId,
                $event->eventId,
                $event->changedDates,
                $event->startDate,
                $event->endDate,
                $event->dateOfChange,
            );
        }
    }

    public function whenEventWasRemoved(EventWasRemoved $event): void
    {
        $this->eventOccurrenceRepository->removeAllOccurrencesByEventId($event->eventId);
    }

    public function whenTimeHasPassed(TimeHasPassed $event): void
    {
        $this->eventOccurrenceRepository->continueAllRecurrenceRules($event->dateTime);
    }
}
