import { RRule } from "rrule"

export type OccurenceState = {
    startDate?: Date,
    endDate?: Date,
    recurrenceRule?: RRule,
    recurrenceDates?: (Date | null)[],
}

export type OccurenceCommitObject = {
    startDate?: Date,
    endDate?: Date,
    recurrenceRule?: string,
    recurrenceDates?: Date[],
}

export type Props<T> = {
    value: T
    commit: (value?: T | null) => void
}

export type OccurenceMethod = 'never' | 'rrule' | 'manual';

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