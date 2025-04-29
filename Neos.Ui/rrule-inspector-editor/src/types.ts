import { RRule } from "rrule"

export type RRuleEditorProps<T> = {
    value: T
    commit: (value?: T | null) => void
}

export type RRuleTab = 'repeat' | 'end'

export interface TabContentProps {
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