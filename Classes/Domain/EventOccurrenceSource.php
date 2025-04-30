<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain;

enum EventOccurrenceSource: string
{
    /** describes the initial occurrence of an event, defined by its startDate, @see https://icalendar.org/iCalendar-RFC-5545/3-8-2-4-date-time-start.html */
    case SOURCE_INITIAL_DATE = 'initialDate';

    /** describes the manually set recurrences by date, @see https://icalendar.org/iCalendar-RFC-5545/3-8-5-2-recurrence-date-times.html */
    case SOURCE_RECURRENCE_DATE = 'recurrenceDate';

    /** describes the automatically set recurrences by rule, @see https://icalendar.org/iCalendar-RFC-5545/3-8-5-3-recurrence-rule.html */
    case SOURCE_RECURRENCE_RULE = 'recurrenceRule';
}
