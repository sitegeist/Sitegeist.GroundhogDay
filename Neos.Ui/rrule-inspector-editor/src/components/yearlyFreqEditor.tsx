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
import { getInitialYearFrequencyType } from '../utils/getInitialYearFrequencyType';
import { useI18n } from '@sitegeist/groundhogday-neos-bridge';


export const YearlyFreqEditor: React.FC<TabContentProps> = ({ rrule, onChange }) => {
    const [yearlyFreqType, setyearlyFreqType] = useState<YearlyFrequencyType>(getInitialYearFrequencyType(rrule));
    const i18n = useI18n();

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
                prefix={i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.every')}
                value={rrule.options.interval ?? 1}
                onChange={handleIntervalChange}
                suffix={i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.years')}
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
                    title={i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.onMonths')}
                    id="bymonths"
                >
                    <Container>
                        <MonthSelector rrule={rrule} onChange={onChange} />
                        <MonthdaySelector rrule={rrule} onChange={onChange} />
                    </Container>
                </Tabs.Panel>
                <Tabs.Panel
                    title={i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.onThe')}
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
