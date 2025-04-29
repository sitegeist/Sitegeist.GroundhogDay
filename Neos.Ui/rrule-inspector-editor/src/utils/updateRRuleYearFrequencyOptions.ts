import { RRule } from 'rrule'
import { YearlyFrequencyType } from '../types'

export const updateRRuleYearFrequencyOptions = (rrule: RRule, type: YearlyFrequencyType): RRule => {
    const baseOptions = { ...rrule.options }

    switch (type) {
        case YearlyFrequencyType.BYMONTHS:
            return new RRule({ ...baseOptions, bymonthday: 1, bysetpos: null, byweekday: null })
        case YearlyFrequencyType.BYSETPOS:
            return new RRule({ ...baseOptions, bymonthday: null, bysetpos: 1, byweekday: null, bymonth: null })
        default:
            return rrule
    }
}
