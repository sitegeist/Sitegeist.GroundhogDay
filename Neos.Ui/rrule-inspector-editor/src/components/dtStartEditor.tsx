import React from 'react'
import { DateInput, Label } from '@neos-project/react-ui-components'
import { RRule } from 'rrule'
import { TabContentProps } from '../types'
import { Container } from './container'

export const DTStartEditor: React.FC<TabContentProps> = ({ rrule, onChange }) => {
    const handleDateChange = (date: Date | null) => {
        const validDate = date ?? new Date()

        const updatedRRule = new RRule({
            ...rrule.options,
            dtstart: validDate,
        })

        onChange(updatedRRule)
    }

    return (
        <Container>
            <Label>{'Beginn'}</Label>
            <DateInput
                theme={{
                    'selectTodayBtn': 'select-tdy-btn'
                }}
                is24Hour
                value={rrule.options.dtstart ?? undefined}
                labelFormat="DD. MMMM YYYY, HH:mm"
                onChange={handleDateChange}
                placeholder="Select a date"
            />
        </Container>
    )
}
