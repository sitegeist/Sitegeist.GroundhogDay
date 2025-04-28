import React, { useState, useEffect } from 'react'
import { Container } from '../components/container'
import { RRuleEditorProps, RRuleEndType, TabId } from '../types'
import { RRule } from 'rrule'
import { Tabs } from '@neos-project/react-ui-components'
import { StartTabContent } from '../components/startTabContent'
import { RepeatTabContent } from '../components/repeatTabContent'
import { EndTabContent } from '../components/endTabContent'
import { useExternalRRule } from '../hooks/useExternalRRule'

export const RRuleEditor: React.FC<RRuleEditorProps<string>> = ({ value, commit }) => {
    const externalValue: RRule = useExternalRRule(value)

    const [rrule, setRRule] = useState<RRule>(externalValue)
    const [activeTab, setActiveTab] = useState('repeat')

    const handleRRuleChange = (updatedRule: RRule) => {
        setRRule(updatedRule)
        commit(updatedRule.toString())
    }

    useEffect(() => {
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
                        rrule={rrule}
                        onChange={handleRRuleChange}
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
