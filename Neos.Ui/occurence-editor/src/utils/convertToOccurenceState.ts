import { RRule } from 'rrule';
import { OccurenceCommitObject, OccurenceState } from '../types';
import { parseICalDate, parseICalDuration } from '../utils/iCalDateHelpers';
import { deserializeExdatesFromString, deserializeRdatesFromString } from '../utils/iCalDateHelpers';

export function convertToOccurenceState(value: OccurenceCommitObject): OccurenceState {
    const { count, unit } = parseICalDuration(value?.duration);

    const stripSeconds = (date: Date) => {
        date.setSeconds(0);
        date.setMilliseconds(0);
        return date;
    };

    return {
        startDate: value?.startDate ? parseICalDate(value.startDate) : stripSeconds(new Date),
        endDate: value?.endDate ? parseICalDate(value.endDate) : undefined,
        durationCount: count,
        durationUnit: unit,
        recurrenceRule: value?.recurrenceRule ? RRule.fromString(value.recurrenceRule) : undefined,
        recurrenceDateTimes: value.recurrenceDateTimes ? deserializeRdatesFromString(value.recurrenceDateTimes) : undefined,
        exceptionDateTimes: value.exceptionDateTimes ? deserializeExdatesFromString(value.exceptionDateTimes) : undefined,
    };
}