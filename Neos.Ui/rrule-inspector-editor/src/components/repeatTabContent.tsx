import React from 'react'
import { Frequency, RRule } from 'rrule'
import { TabContentProps } from '../types'
import { SelectBox } from '@neos-project/react-ui-components'
import { getFreqTypeOptions } from '../utils/constants'
import { Counter } from './counter'
import WeekdaySelector from './weekdaySelector'
import { Container } from './container'
import { MonthFrequencyEditor } from './monthFrequencyEditor'
import { YearlyFreqEditor } from './yearlyFreqEditor'
import { useI18n } from '@sitegeist/groundhogday-neos-bridge'

export const RepeatTabContent: React.FC<TabContentProps> = ({ rrule, onChange }) => {
    const i18n = useI18n();
    
    const handleIntervalChange = (newInterval: number) => {
        const updatedRRule = new RRule({
            ...rrule.options,
            interval: newInterval
        })
        onChange(updatedRRule)
    }

    const handleFrequencyTypeChange = (frequency: Frequency) => {
        if (rrule.options.freq === frequency) {
            return;
        }

        const updatedRRule = new RRule({
            ...rrule.options,
            freq: frequency,
            byweekday: undefined,
            interval: 1,
            bymonthday: frequency === Frequency.MONTHLY ? 1 : undefined,
            bymonth: undefined,
            bysetpos: undefined
        })
        onChange(updatedRRule)
    }

    return (
        <Container>
            <SelectBox
                value={rrule.options.freq}
                options={getFreqTypeOptions(i18n)}
                onValueChange={handleFrequencyTypeChange}
            />

            {rrule.options.freq == Frequency.HOURLY &&
                <Counter
                    prefix={i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.every')}
                    value={rrule.options.interval ?? 0}
                    onChange={handleIntervalChange}
                    suffix={i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.hours')}
                />
            }

            {rrule.options.freq == Frequency.DAILY &&
                <Counter
                    prefix={i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.every')}
                    value={rrule.options.interval ?? 0}
                    onChange={handleIntervalChange}
                    suffix={i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.days')}
                />
            }

            {rrule.options.freq == Frequency.WEEKLY &&
                <Counter
                    prefix={i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.every')}
                    value={rrule.options.interval ?? 0}
                    onChange={handleIntervalChange}
                    suffix={i18n('Sitegeist.GroundhogDay:NodeTypes.Mixin.Event:inspector.weeks')}
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
