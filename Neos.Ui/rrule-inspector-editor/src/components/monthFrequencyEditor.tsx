import React from 'react'
import { useState, useEffect } from 'react';
import { RRule, Frequency } from 'rrule';
import { MonthFrequencyType, TabContentProps } from '../types';
import { Counter } from './counter';
import { Container } from './container';
import { CheckBox } from '@neos-project/react-ui-components';


export const MonthFrequencyEditor: React.FC<TabContentProps> = ({ rrule, onChange }) => {
    const [freqMonthType, setFreqMonthType] = useState<MonthFrequencyType>(
        rrule.options.bymonthday ? 'bymonthday' : 'bysetpos'
    );

    useEffect(() => {
        if (rrule.options.bymonthday && freqMonthType !== 'bymonthday') {
            setFreqMonthType('bymonthday');
        }
        if (rrule.options.bysetpos && freqMonthType !== 'bysetpos') {
            setFreqMonthType('bysetpos');
        }
    }, [rrule]);

    const handleFreqMonthTypeChange = (type: MonthFrequencyType) => {
        const updatedOptions = {
            ...rrule.options,
            bymonthday: type === 'bymonthday' ? [1] : undefined,
            bysetpos: type === 'bysetpos' ? [1] : undefined,
            byweekday: type === 'bysetpos' ? [RRule.MO] : undefined,
        };

        const updatedRRule = new RRule(updatedOptions);
        onChange(updatedRRule);
        setFreqMonthType(type);
    };

    const handleIntervalChange = (interval: number) => {
        const updatedRRule = new RRule({
            ...rrule.options,
            interval,
        });
        onChange(updatedRRule);
    };

    if (rrule.options.freq !== Frequency.MONTHLY) {
        return null;
    }

    return (
        <Container>
            <Counter
                prefix="Every"
                value={rrule.options.interval ?? 1}
                onChange={handleIntervalChange}
                suffix="Month(s)"
            />

            <CheckBox />
            <CheckBox />
        </Container>
    );
}
