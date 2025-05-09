import React, { createContext, useContext, useState, ReactNode, useEffect } from 'react';
import { DurationUnit, OccurenceCommitObject, OccurenceState } from '../types';
import { RRule } from 'rrule';
import { convertToOccurenceState } from '../utils/convertToOccurenceState';

type OccurenceContextType = {
    occurence: OccurenceState;
    setStartDate: (date: Date) => void;
    setEndDate: (date: Date | undefined) => void;
    setRRule: (rrule: RRule) => void;
    resetRRule: () => void;
    setRecurrencDates: (dates:  (Date | null)[]) => void;
    setExceptionDates: (dates:  (Date | null)[]) => void;
    setDurationValues: (count?: number, unit?: DurationUnit) => void;
    resetDurationValues: () => void;
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
    const [occurence, setOccurence] = useState<OccurenceState>(() => convertToOccurenceState(value));

    useEffect(() => {
        setOccurence(convertToOccurenceState(value));
    }, [value, setOccurence]);

    const setStartDate = (startDate: Date) => {
        const next = { ...occurence, startDate };
        setOccurence(next);
        onCommit(next);
    };
    
    const setEndDate = (endDate: Date | undefined) => {
        const next = { ...occurence, endDate: endDate };
        setOccurence(next);
        onCommit(next);
    };

    const setDurationValues = (count?: number, unit?: DurationUnit) => {
        const next = { 
            ...occurence,
            durationCount: count,
            durationUnit: unit
        };
        setOccurence(next);
        onCommit(next);
    };

    const resetDurationValues = () => {
        const next = { 
            ...occurence,
            durationCount: undefined,
            DurationUnit: undefined
        };
        setOccurence(next);
        onCommit(next);
    }
    
    const setRRule = (rrule: RRule) => {
        const next = {
            ...occurence,
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

    const resetRRule = () => {
        const next = {
            ...occurence,
            recurrenceRule: undefined
        };
        setOccurence(next);
        onCommit(next);
    }
    
    const setRecurrencDates = (dates: (Date | null)[]) => {
        const next = {
            ...occurence,
            recurrenceDateTimes: dates,
        };
        setOccurence(next);
        onCommit(next);
    };

    const setExceptionDates = (dates: (Date | null)[]) => {
        const next = {
            ...occurence,
            exceptionDateTimes: dates,
        };
        setOccurence(next);
        onCommit(next);
    };

    return (
        <OccurenceContext.Provider 
            value={{
                occurence,
                setStartDate,
                setEndDate,
                setRRule,
                resetRRule,
                setRecurrencDates,
                setExceptionDates,
                setDurationValues,
                resetDurationValues
            }}
        >
            {children}
        </OccurenceContext.Provider>
    );
}