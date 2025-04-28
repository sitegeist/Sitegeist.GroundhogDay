import React from 'react'
import { DateInput } from '@neos-project/react-ui-components'
import { RRule } from 'rrule'
import { TabContentProps } from '../types'

export const StartTabContent: React.FC<TabContentProps> = ({ rrule, onChange }) => {
    const handleDateChange = (date: Date | null) => {
        const validDate = date ?? new Date()

        const updatedRRule = new RRule({
            ...rrule.options,
            dtstart: validDate,
        })

        onChange(updatedRRule)
    }

    return (
        <DateInput
            theme={{
                'selectTodayBtn': 'select-tdy-btn'
            }}
            dateOnly
            value={rrule.options.dtstart ?? undefined}
            labelFormat="DD. MMMM YYYY"
            onChange={handleDateChange}
            placeholder="Select a date"
        />
    )
}
