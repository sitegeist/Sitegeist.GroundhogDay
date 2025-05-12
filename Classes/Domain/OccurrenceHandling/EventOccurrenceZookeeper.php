<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain\OccurrenceHandling;

use Neos\Flow\Annotations as Flow;
use Sitegeist\GroundhogDay\Domain\EventOccurrenceRepository;

/**
 * The event date zookeeper, implementing the policy that whenever
 * * an event was created
 * * an event's occurrence specification was changed
 * * an event was removed
 * * time has passed
 * the event occurrences are to be updated
 */
#[Flow\Scope('singleton')]
final class EventOccurrenceZookeeper
{
    public function __construct(
        private readonly EventOccurrenceRepository $eventOccurrenceRepository,
    ) {
    }

    public function whenEventWasCreated(EventWasCreated $event): void
    {
        $this->eventOccurrenceRepository->initializeOccurrences(
            eventId: $event->eventId,
            calendarId: $event->calendarId,
            occurrenceSpecification: $event->occurrenceSpecification,
            locationTimezone: $event->locationTimezone,
        );
    }

    public function whenEventOccurrenceSpecificationWasChanged(EventOccurrenceSpecificationWasChanged $event): void
    {
        $this->eventOccurrenceRepository->replaceAllFutureOccurrencesByEventId(
            eventId: $event->eventId,
            calendarId: $event->calendarId,
            occurrenceSpecification: $event->occurrenceSpecification,
            referenceDate: $event->dateOfChange,
            locationTimeZone: $event->locationTimezone,
        );
    }

    public function whenTimeHasPassed(TimeHasPassed $event): void
    {
        $this->eventOccurrenceRepository->continueAllRecurrenceRules($event->dateTime);
    }

    public function whenEventWasRemoved(EventWasRemoved $event): void
    {
        $this->eventOccurrenceRepository->removeAllOccurrencesByEventId($event->eventId);
    }
}
