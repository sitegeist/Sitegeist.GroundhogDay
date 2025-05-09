import { RRule } from "rrule"

export type OccurenceState = {
    startDate?: Date,
    endDate?: Date,
    durationCount?: number,
    durationUnit?: DurationUnit,
    recurrenceRule?: RRule,
    recurrenceDateTimes?: (Date | null)[],
    exceptionDateTimes?: (Date | null)[]
}

export type OccurenceCommitObject = {
    startDate: string,
    endDate?: string | null,
    duration?: string | null,
    recurrenceRule?: string | null,
    recurrenceDateTimes?: string | null,
    exceptionDateTimes?: string | null
}

export type Props<T> = {
    value: T
    commit: (value?: T | null) => void
}

export type OccurenceMethod = 'never' | 'rrule';

export type EventEndType = 'endDate' | 'duration';

export type DurationUnit = 'minute' | 'hour' | 'day';

export type RRuleTab = 'repeat' | 'end';

export interface RRuleEditorComponentProps {
    rrule: RRule;
    onChange: (updatedRRule: RRule) => void;
}

export type RRuleEndType = 'until' | 'count' | 'never';

export enum MonthFrequencyType {
    BYMONTHDAY = 'bymonthday',
    BYSETPOS = 'bysetpos',
}

export enum YearlyFrequencyType {
    BYMONTHS = 'bymonths',
    BYSETPOS = 'bysetpos',
}

export type OccurenceEditorOptions = {
    disabled: boolean;
}