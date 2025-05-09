import { SynchronousRegistry } from '@neos-project/neos-ui-extensibility'
import * as React from 'react'
import { IGlobalRegistry, NeosContext } from '@sitegeist/groundhogday-neos-bridge'
import { OcurrenceEditor } from './editors/OccurenceEditor'
import { OccurenceProvider } from './context/OccurenceContext'
import { OccurenceCommitObject, OccurenceState } from './types'
import { serializeExdatesToString, serializeRdatesToString } from './utils/iCalDateHelpers'
import { formatICalDate, formatICalDuration } from './utils/iCalDateHelpers'
import _ from 'lodash'

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
            const { value, commit, options, highlight } = props;
            
            const handleCommit = (occurence: OccurenceState) => {
                if (!occurence.startDate) {
                    commit(value);
                    return;
                }

                const occurenceCommit: OccurenceCommitObject = {
                    startDate: formatICalDate(occurence.startDate),
                    endDate: occurence.endDate ? formatICalDate(occurence.endDate) : null,
                    recurrenceRule: occurence.recurrenceRule?.toString() ?? null,
                    recurrenceDateTimes: serializeRdatesToString(occurence.recurrenceDateTimes),
                    exceptionDateTimes: serializeExdatesToString(occurence.exceptionDateTimes),
                    duration: (occurence.durationCount && occurence.durationUnit) ? formatICalDuration(occurence.durationCount, occurence.durationUnit) : null
                }
                
                if (!_.isEqual(value, occurenceCommit)) {
                    commit(occurenceCommit);
                }
            }

            return (
                <NeosContext.Provider value={{globalRegistry}}>
                    <OccurenceProvider value={value} onCommit={handleCommit}>
                        <OcurrenceEditor options={options} highlight={highlight} />
                    </OccurenceProvider>
                </NeosContext.Provider>
            )
        },
    })
}
