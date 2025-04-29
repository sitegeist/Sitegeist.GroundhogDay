import { SynchronousRegistry } from '@neos-project/neos-ui-extensibility'
import * as React from 'react'
import { RRuleEditor } from './editors/RRuleEditor'
import { IGlobalRegistry, NeosContext } from '@sitegeist/groundhogday-neos-bridge'

export function registerRRulEditor(globalRegistry: IGlobalRegistry): void {
    const inspectorRegistry = globalRegistry.get('inspector')
    if (!inspectorRegistry) {
        console.warn('[Sitegeist.Groundhogday.RRuleEditor]: Could not find inspector registry.')
        console.warn('[Sitegeist.Groundhogday.RRuleEditor]: Skipping registration of RRuleEditor...')
        return
    }

    const editorsRegistry = inspectorRegistry.get<SynchronousRegistry<any>>('editors')
    if (!editorsRegistry) {
        console.warn('[Sitegeist.Groundhogday.RRuleEditor]: Could not find inspector editors registry.')
        console.warn('[Sitegeist.Groundhogday.RRuleEditor]: Skipping registration of RRuleEditor...')
        return
    }

    editorsRegistry.set('Sitegeist.Groundhogday/Inspector/Editors/RRuleEditor', {
        component: (props: any) => {
            const { value, ...rest } = props

            return (
                <NeosContext.Provider value={{globalRegistry}}>
                    <RRuleEditor {...rest} value={value} />
                </NeosContext.Provider>
            )
        },
    })
}
