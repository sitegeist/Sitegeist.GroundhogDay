import React, { useState } from 'react'
import { DateInput, SelectBox } from '@neos-project/react-ui-components'
import { RRule } from 'rrule'
import { RRuleEndType, TabContentProps } from '../types'
import { Counter } from './counter'
import { getInitialEndType } from '../utils/getInitialEndType'
import { updateRRuleEndOptions } from '../utils/updateRRuleEndOptions'
import { getEndTypeOptions } from '../utils/constants'
import { Container } from './container'
import { useI18n } from '@sitegeist/groundhogday-neos-bridge'

export const EndTabContent: React.FC<TabContentProps> = ({ rrule, onChange }) => {
    const [endType, setEndType] = useState<RRuleEndType>(getInitialEndType(rrule))
    const i18n = useI18n()

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
        <Container>
            <SelectBox
                value={endType}
                options={getEndTypeOptions(i18n)}
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
                    is24Hour
                    value={rrule.options.until ?? undefined}
                    labelFormat="DD. MMMM YYYY HH:mm"
                    onChange={handleUntilChange}
                    placeholder={i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.selectDate')}
                />
            )}

            {endType === 'count' && (
                <Counter
                    prefix={i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.after')}
                    value={rrule.options.count ?? 0}
                    onChange={handleCountChange}
                    suffix={i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.occurences')}
                />
            )}
        </Container>
    )
}
