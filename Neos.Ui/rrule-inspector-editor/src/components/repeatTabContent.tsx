import React from 'react'
import { Frequency, RRule } from 'rrule'
import { TabContentProps } from '../types'
import { SelectBox } from '@neos-project/react-ui-components'
import { FREQ_TYPE_OPTIONS } from '../utils/constants'
import { Counter } from './counter'
import WeekdaySelector from './weekdaySelector'
import { Container } from './container'
import { MonthFrequencyEditor } from './monthFrequencyEditor'
import MonthSelector from './monthSelector'
import MonthdaySelector from './monthDaySelector'
import { YearlyFreqEditor } from './yearlyFreqEditor'

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
            freq: frequency,
            byweekday: undefined,
            interval: undefined,
            bymonthday: frequency === Frequency.MONTHLY ? 1 : undefined
        })
        onChange(updatedRRule)
    }

    return (
        <Container>
            <SelectBox
                value={rrule.options.freq}
                options={FREQ_TYPE_OPTIONS}
                onValueChange={handleFrequencyTypeChange}
            />

            {rrule.options.freq == Frequency.HOURLY &&
                <Counter
                    prefix={'Every'}
                    value={rrule.options.interval ?? 0}
                    onChange={handleIntervalChange}
                    suffix={'Hour(s)'}
                />
            }

            {rrule.options.freq == Frequency.DAILY &&
                <Counter
                    prefix={'Every'}
                    value={rrule.options.interval ?? 0}
                    onChange={handleIntervalChange}
                    suffix={'Day(s)'}
                />
            }

            {rrule.options.freq == Frequency.WEEKLY &&
                <Counter
                    prefix={'Every'}
                    value={rrule.options.interval ?? 0}
                    onChange={handleIntervalChange}
                    suffix={'Week(s)'}
                />
            }

            {rrule.options.freq == Frequency.MONTHLY &&
                <MonthFrequencyEditor rrule={rrule} onChange={onChange} />
            }
            
            {rrule.options.freq == Frequency.WEEKLY &&
                <WeekdaySelector rrule={rrule} onChange={onChange} />
            }

            {rrule.options.freq == Frequency.YEARLY &&
                <YearlyFreqEditor rrule={rrule} onChange={onChange} />
            }
        </Container>        
    )
}
