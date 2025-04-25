import React, { useState, useEffect } from 'react'
import { Container } from '../components/container'
import { RRuleEditorProps, RRuleEndType } from '../types'
import { RRule } from 'rrule'
import { Tabs } from '@neos-project/react-ui-components'
import { StartTabContent } from '../components/startTabContent'
import { RepeatTabContent } from '../components/repeatTabContent'
import { EndTabContent } from '../components/endTabContent'

export const RRuleEditor: React.FC<RRuleEditorProps<string>> = ({ value, commit }) => {
    const [rrule, setRRule] = useState<RRule>(() => RRule.fromString(value))
    const [activeTab, setActiveTab] = useState('repeat')

    useEffect(() => {
        setRRule(RRule.fromString(value))
    }, [value])

    const handleDTStartChange = (e: Date) => {
        const updated = new RRule({
            ...rrule.options,
            dtstart: e,
        })
        setRRule(updated)
        setActiveTab('repeat')
        commit(updated.toString())
    }

    const handleRRuleChange = (updatedRule: RRule) => {
        setRRule(updatedRule)
        commit(updatedRule.toString())
    }

    useEffect(() => {
        // Only for debug
        console.log(rrule.toString())
    }, [rrule])

    return (
        <Container>
            <Tabs  
                activeTab={activeTab}
                onActiveTabChange={(id: RRuleEndType) => setActiveTab(id)}
                theme={{
                    'tabNavigation__item': 'tabs-nav-item',
                    'tabNavigation__itemBtn': 'tabs-nav-item-btn',
                    'tabs__content': 'tabs-content'
                }}
            >
                <Tabs.Panel
                    title="Start"
                    id="start"
                >
                    <StartTabContent 
                        value={rrule.options.dtstart ?? undefined}
                        onChange={handleDTStartChange}
                    />
                </Tabs.Panel>
                <Tabs.Panel
                    title="Repeat"
                    id="repeat"
                >
                    <RepeatTabContent
                        rrule={rrule}
                        onChange={handleRRuleChange}
                    />
                </Tabs.Panel>
                <Tabs.Panel
                    title="End"
                    id="end"
                >
                    <EndTabContent
                        rrule={rrule} 
                        onChange={handleRRuleChange} 
                    />
                </Tabs.Panel>
            </Tabs>
        </Container>
    )
}
