import { OccurenceState, OccurenceMethod } from "../types"

export const getInitialOccurenceMethod = (occurence: OccurenceState): OccurenceMethod => {
    if (occurence.recurrenceRule) return 'rrule';
    if (occurence.recurrenceDates) return 'manual';
    return 'never';
}
