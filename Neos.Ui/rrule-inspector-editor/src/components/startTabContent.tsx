import React from 'react'
import { DateInput } from '@neos-project/react-ui-components'

interface StartTabContentProps {
    value: Date;
    onChange: (date: Date) => void;
}

export const StartTabContent: React.FC<StartTabContentProps> = (
    { value, onChange }
) => {
    return (
        <DateInput
            theme={{
                'selectTodayBtn': 'select-tdy-btn'
            }}
            dateOnly={true}
            value={value}
            labelFormat="DD. MMMM YYYY"
            onChange={onChange}
            placeholder='Select a date'
        />
    )
}