import { RRule, RRuleSet } from "rrule"

export interface INodeType {
    name: string
    label: string
    ui?: {
        icon?: string
    }
}

export interface INodeTypesRegistry {
    get: (key: string) => undefined | INodeType
    getAllAsList: () => INodeType[]
    isOfType: (name: string, reference: string) => boolean
    getSubTypesOf: (name: string) => string[]
    getRole(roleName: string): string
}

export interface IGlobalRegistry {
    get(key: string):
        | {
              get: <T>(key: string) => T
              getAllAsList: <T>() => T[]
              set(key: string, value: any): void
          }
        | undefined
    get(key: '@neos-project/neos-ui-contentrepository'): INodeTypesRegistry
    set(key: string, value: any): void
}

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