import React from 'react'
import { useState } from 'react';
import { RRule } from 'rrule';
import { MonthFrequencyType, TabContentProps } from '../types';
import { Counter } from './counter';
import { Container } from './container';
import { Tabs } from '@neos-project/react-ui-components';
import MonthdaySelector from './monthDaySelector';
import { getInitialMonthFrequencyType } from '../utils/getInitialMonthFrequencyType';
import SetPosSelector from './setPosSelector';
import { updateRRuleMonthFrequencyOptions } from '../utils/updateRRuleMonthFrequencyOptions';


export const MonthFrequencyEditor: React.FC<TabContentProps> = ({ rrule, onChange }) => {
    const [freqMonthType, setFreqMonthType] = useState<MonthFrequencyType>(getInitialMonthFrequencyType(rrule));

    const handleIntervalChange = (interval: number) => {
        const updatedRRule = new RRule({
            ...rrule.options,
            interval,
        });
        onChange(updatedRRule);
    };

    return (
        <Container>
            <Counter
                prefix="Every"
                value={rrule.options.interval ?? 1}
                onChange={handleIntervalChange}
                suffix="Month(s)"
            />

            <Tabs  
                activeTab={freqMonthType}
                onActiveTabChange={(type: MonthFrequencyType) => {
                    setFreqMonthType(type)
                    onChange(updateRRuleMonthFrequencyOptions(rrule, type))
                }}
                theme={{
                    'tabNavigation__item': 'tabs-nav-item',
                    'tabNavigation__itemBtn': 'tabs-nav-item-btn',
                    'tabs__content': 'tabs-content'
                }}
            >
                <Tabs.Panel
                    title="By Day(s)"
                    id="bymonthday"
                >
                    <MonthdaySelector rrule={rrule} onChange={onChange} />
                </Tabs.Panel>
                <Tabs.Panel
                    title="On nth Day"
                    id="bysetpos"
                >
                    <SetPosSelector rrule={rrule} onChange={onChange} />
                </Tabs.Panel>
            </Tabs>
        </Container>
    );
}
