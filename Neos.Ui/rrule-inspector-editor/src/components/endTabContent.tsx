import React, { useState } from 'react'
import { DateInput, SelectBox } from '@neos-project/react-ui-components'
import { RRule } from 'rrule'
import { RRuleEndType, TabContentProps } from '../types'
import { EndTabContainer } from './endTabContainer'
import { Counter } from './counter'
import { getInitialEndType } from '../utils/getInitialEndType'
import { updateRRuleEndOptions } from '../utils/updateRRuleEndOptions'
import { END_TYPE_OPTIONS } from '../utils/constants'

export const EndTabContent: React.FC<TabContentProps> = ({ rrule, onChange }) => {
    const [endType, setEndType] = useState<RRuleEndType>(getInitialEndType(rrule))

    const handleUntilChange = (newDate: Date) => {
        const updatedRRule = new RRule({
            ...rrule.options,
            until: newDate,
            count: undefined,
        })
        onChange(updatedRRule)
    }

    const handleCountChange = (newCount: number) => {
        const updatedRRule = new RRule({
            ...rrule.options,
            count: newCount,
            until: undefined,
        })
        onChange(updatedRRule)
    }

    return (
        <EndTabContainer>
            <SelectBox
                value={endType}
                options={END_TYPE_OPTIONS}
                onValueChange={(value: RRuleEndType) => {
                    setEndType(value)
                    onChange(updateRRuleEndOptions(rrule, value))
                }}
            />

            {endType === 'until' && (
                <DateInput
                    theme={{
                        'selectTodayBtn': 'select-tdy-btn',
                    }}
                    dateOnly={true}
                    value={rrule.options.until ?? undefined}
                    labelFormat="DD. MMMM YYYY"
                    onChange={handleUntilChange}
                    placeholder='Select a date'
                />
            )}

            {endType === 'count' && (
                <Counter
                    value={rrule.options.count ?? 0}
                    onChange={handleCountChange}
                />
            )}
        </EndTabContainer>
    )
}
