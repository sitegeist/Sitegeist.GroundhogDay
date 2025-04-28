import { RRule } from 'rrule'
import { MonthFrequencyType } from '../types'

export const updateRRuleMonthFrequencyOptions = (rrule: RRule, type: MonthFrequencyType): RRule => {
    const baseOptions = { ...rrule.options }

    switch (type) {
        case MonthFrequencyType.BYMONTHDAY:
            return new RRule({ ...baseOptions, bymonthday: 1, bysetpos: null, byweekday: null })
        case MonthFrequencyType.BYSETPOS:
            return new RRule({ ...baseOptions, bymonthday: null, bysetpos: 1, byweekday: 0 })
        default:
            return rrule
    }
}
