import React, { useState, useEffect } from 'react'
import { EditorContainer } from '../components/container'
import { RRuleEditorProps, RRuleEndType, RRuleTab } from '../types'
import { RRule } from 'rrule'
import { Tabs } from '@neos-project/react-ui-components'
import { RepeatTabContent } from '../components/repeatTabContent'
import { EndTabContent } from '../components/endTabContent'
import { useExternalRRule } from '../hooks/useExternalRRule'
import { DTStartEditor } from '../components/dtStartEditor'

export const RRuleEditor: React.FC<RRuleEditorProps<string>> = ({ value, commit }) => {
    const externalValue: RRule = useExternalRRule(value)

    const [rrule, setRRule] = useState<RRule>(externalValue)
    const [activeTab, setActiveTab] = useState<RRuleTab>('repeat')

    const handleRRuleChange = (updatedRule: RRule) => {
        setRRule(new RRule({
            ...updatedRule.options,
            byhour: undefined,
            byminute: undefined,
            bysecond: undefined,
            wkst: undefined
        }))
        commit(updatedRule.toString())
    }

    return (
        <EditorContainer>
            <DTStartEditor rrule={rrule} onChange={handleRRuleChange} />
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
        </EditorContainer>
    )
}
