import React, { useState, useEffect } from 'react'
import { Container } from '../components/container'
import { RRuleTab } from '../types'
import { RRule } from 'rrule'
import { Tabs } from '@neos-project/react-ui-components'
import { RepeatTabContent } from '../components/repeatTabContent'
import { EndTabContent } from '../components/endTabContent'
import { useI18n } from '@sitegeist/groundhogday-neos-bridge'
import { useOccurence } from '../context/OccurenceContext'

export const RRuleEditor = () => {
    const { occurence, setRRule } = useOccurence();
    const i18n = useI18n();

    const [activeTab, setActiveTab] = useState<RRuleTab>('repeat')

    const handleRRuleChange = (updatedRule: RRule) => {
        setRRule(new RRule({
            ...updatedRule.options,
            byhour: null,
            byminute: null,
            bysecond: null,
            wkst: null
        }))
    }

    if (!occurence.recurrenceRule) return null;

    return (
        <Container>
            <Tabs  
                activeTab={activeTab}
                onActiveTabChange={(id: RRuleTab) => setActiveTab(id)}
                theme={{
                    'tabNavigation__item': 'tabs-nav-item',
                    'tabNavigation__itemBtn': 'tabs-nav-item-btn',
                    'tabs__content': 'tabs-content'
                }}
            >
                <Tabs.Panel
                    title={i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.repeat')}
                    id="repeat"
                >
                    <RepeatTabContent
                        rrule={occurence.recurrenceRule}
                        onChange={handleRRuleChange}
                    />
                </Tabs.Panel>
                <Tabs.Panel
                    title={i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.end')}
                    id="end"
                >
                    <EndTabContent
                        rrule={occurence.recurrenceRule} 
                        onChange={handleRRuleChange} 
                    />
                </Tabs.Panel>
            </Tabs>
        </Container>
    )
}
