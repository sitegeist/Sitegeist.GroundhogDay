import { SynchronousRegistry } from '@neos-project/neos-ui-extensibility'
import * as React from 'react'
import { IGlobalRegistry, NeosContext } from '@sitegeist/groundhogday-neos-bridge'
import { OcurrenceEditor } from './editors/OccurenceEditor'
import { OccurenceProvider } from './context/OccurenceContext'
import { OccurenceCommitObject, OccurenceState } from './types'
import { normalizeDates } from './utils/normalizeRecurrenceDates'
import { format } from 'date-fns'

export function registerOccurenceEditor(globalRegistry: IGlobalRegistry): void {
    const inspectorRegistry = globalRegistry.get('inspector')
    if (!inspectorRegistry) {
        console.warn('[Sitegeist.Groundhogday.OccurenceEditor]: Could not find inspector registry.')
        console.warn('[Sitegeist.Groundhogday.OccurenceEditor]: Skipping registration of OccurenceEditor...')
        return
    }

    const editorsRegistry = inspectorRegistry.get<SynchronousRegistry<any>>('editors')
    if (!editorsRegistry) {
        console.warn('[Sitegeist.Groundhogday.OccurenceEditor]: Could not find inspector editors registry.')
        console.warn('[Sitegeist.Groundhogday.OccurenceEditor]: Skipping registration of OccurenceEditor...')
        return
    }

    editorsRegistry.set('Sitegeist.Groundhogday/Inspector/Editors/OccurenceEditor', {
        component: (props: any) => {
            const { value, commit, ...rest } = props;

            const handleCommit = (occurence: OccurenceState) => {
                const occurenceCommit: OccurenceCommitObject = {
                    startDate: format(occurence.startDate ?? new Date, 'YmdHisp'),
                    endDate: occurence.endDate && format(occurence.endDate, 'YmdHisp'),
                    recurrenceRule: occurence.recurrenceRule?.toString() ?? undefined,
                    recurrenceDates: normalizeDates(occurence.recurrenceDates)
                }

                commit(occurenceCommit);
            }

            return (
                <NeosContext.Provider value={{globalRegistry}}>
                    <OccurenceProvider value={value} onCommit={handleCommit}>
                        <OcurrenceEditor {...rest} value={value} />
                    </OccurenceProvider>
                </NeosContext.Provider>
            )
        },
    })
}
