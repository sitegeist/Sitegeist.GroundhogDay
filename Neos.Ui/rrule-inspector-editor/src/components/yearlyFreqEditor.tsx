import React from 'react'
import { useState } from 'react';
import { RRule } from 'rrule';
import { TabContentProps, YearlyFrequencyType } from '../types';
import { Counter } from './counter';
import { Container } from './container';
import { Tabs } from '@neos-project/react-ui-components';
import MonthdaySelector from './monthDaySelector';
import SetPosSelector from './setPosSelector';
import MonthSelector from './monthSelector';
import { updateRRuleYearFrequencyOptions } from '../utils/updateRRuleYearFrequencyOptions';


export const YearlyFreqEditor: React.FC<TabContentProps> = ({ rrule, onChange }) => {
    const [yearlyFreqType, setyearlyFreqType] = useState<YearlyFrequencyType>();

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
                suffix="Year(s)"
            />

            <Tabs  
                activeTab={yearlyFreqType}
                onActiveTabChange={(type: YearlyFrequencyType) => {
                    setyearlyFreqType(type)
                    onChange(updateRRuleYearFrequencyOptions(rrule, type))
                }}
                theme={{
                    'tabNavigation__item': 'tabs-nav-item',
                    'tabNavigation__itemBtn': 'tabs-nav-item-btn',
                    'tabs__content': 'tabs-content'
                }}
            >
                <Tabs.Panel
                    title="By Month(s)"
                    id="bymonths"
                >
                    <Container>
                        <MonthSelector rrule={rrule} onChange={onChange} />
                        <MonthdaySelector rrule={rrule} onChange={onChange} />
                    </Container>
                </Tabs.Panel>
                <Tabs.Panel
                    title="On nth Day"
                    id="bysetpos"
                >
                    <Container>
                        <SetPosSelector rrule={rrule} onChange={onChange} />
                        <MonthSelector rrule={rrule} onChange={onChange} />
                    </Container>
                </Tabs.Panel>
            </Tabs>
        </Container>
    );
}
