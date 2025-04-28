import React from 'react'
import { Frequency, RRule } from 'rrule'
import { TabContentProps } from '../types'
import { SelectBox } from '@neos-project/react-ui-components'
import { REPEAT_TYPE_OPTIONS } from '../utils/constants'
import { Counter } from './counter'

export const RepeatTabContent: React.FC<TabContentProps> = ({ rrule, onChange }) => {
    const handleIntervalChange = (newInterval: number) => {
        const updatedRRule = new RRule({
            ...rrule.options,
            interval: newInterval
        })
        onChange(updatedRRule)
    }

    const handleFrequencyTypeChange = (frequency: Frequency) => {
        const updatedRRule = new RRule({
            ...rrule.options,
            freq: frequency
        })
        onChange(updatedRRule)
    }

    return (
        <>
            <SelectBox
                value={rrule.options.freq}
                options={REPEAT_TYPE_OPTIONS}
                onValueChange={handleFrequencyTypeChange}
            />

            {(
                rrule.options.freq == Frequency.HOURLY || 
                rrule.options.freq == Frequency.DAILY ||
                rrule.options.freq == Frequency.WEEKLY
            ) &&
                <Counter
                    value={rrule.options.interval ?? 0}
                    onChange={handleIntervalChange}
                />
            }
            
            {rrule.options.freq == Frequency.WEEKLY &&
                <div>

                </div>
            }
        </>        
    )
}