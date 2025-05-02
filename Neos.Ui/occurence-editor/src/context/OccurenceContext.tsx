import React, { createContext, useContext, useState, ReactNode, Dispatch, SetStateAction, useEffect } from 'react';
import { OccurenceCommitObject, OccurenceState } from '../types';
import { RRule } from 'rrule';

type OccurenceContextType = {
    occurence: OccurenceState;
    setStartDate: (date: Date) => void;
    setEndDate: (date: Date) => void;
    setRRule: (rrule: RRule) => void;
    setRecurrencDates: (dates:  (Date | null)[]) => void;
    resetRecurrenceDatesAndRule: () => void;
};

const OccurenceContext = createContext<OccurenceContextType | undefined>(undefined);

export const useOccurence = () => {
    const context = useContext(OccurenceContext);
    if (!context) throw new Error('[Sitegeist.Groundhogday.OccurenceEditor]: useOccurence must be used within OccurenceProvider');
    return context;
}

type OccurenceProviderProps = {
    children: ReactNode;
    onCommit: (occurence: OccurenceState) => void;
    value: OccurenceCommitObject
}

export const OccurenceProvider = (
    { children, onCommit, value }: OccurenceProviderProps
) => {
    const [occurence, setOccurence] = useState<OccurenceState>({
        startDate: value?.startDate,
        endDate: value?.endDate,
        recurrenceRule: value?.recurrenceRule ? RRule.fromString(value?.recurrenceRule) : undefined,
        recurrenceDates: value?.recurrenceDates
    })

    const setStartDate = (startDate: Date) => {
        const next = { ...occurence, startDate };
        setOccurence(next);
        onCommit(next);
    };
    
    const setEndDate = (endDate: Date) => {
        const next = { ...occurence, endDate };
        setOccurence(next);
        onCommit(next);
    };
    
    const setRRule = (rrule: RRule) => {
        const next = {
            ...occurence,
            recurrenceDates: undefined,
            recurrenceRule: new RRule({
                ...rrule.options,
                dtstart: null,
                wkst: null,
                byhour: null,
                byminute: null,
                bysecond: null,
            }),
        };
        setOccurence(next);
        onCommit(next);
    };
    
    const setRecurrencDates = (dates: (Date | null)[]) => {
        const next = {
            ...occurence,
            recurrenceRule: undefined,
            recurrenceDates: dates,
        };
        setOccurence(next);
        onCommit(next);
    };


    const resetRecurrenceDatesAndRule = () => {
        const next = {
            ...occurence,
            recurrenceRule: undefined,
            recurrenceDates: undefined,
        }
        setOccurence(next);
        onCommit(next);
    }

    return (
        <OccurenceContext.Provider 
            value={{occurence, setStartDate, setEndDate, setRRule, setRecurrencDates, resetRecurrenceDatesAndRule}}
        >
            {children}
        </OccurenceContext.Provider>
    );
}