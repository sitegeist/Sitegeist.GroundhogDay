import { SynchronousRegistry } from '@neos-project/neos-ui-extensibility'
import * as React from 'react'
import { TestComponent } from './components/test'

export function registerRRulEditor(globalRegistry: any): void {
    const inspectorRegistry = globalRegistry.get('inspector')
    if (!inspectorRegistry) {
        console.warn('[Sitegeist.Groundhogday.RRuleEditor]: Could not find inspector registry.')
        console.warn('[Sitegeist.Groundhogday.RRuleEditor]: Skipping registration of RRuleEditor...')
        return
    }

    const editorsRegistry = inspectorRegistry.get('editors')
    if (!editorsRegistry) {
        console.warn('[Sitegeist.Groundhogday.RRuleEditor]: Could not find inspector editors registry.')
        console.warn('[Sitegeist.Groundhogday.RRuleEditor]: Skipping registration of RRuleEditor...')
        return
    }

    editorsRegistry.set('Sitegeist.Groundhogday/Inspector/Editors/RRuleEditor', {
        component: () => <TestComponent />,
    })
}
