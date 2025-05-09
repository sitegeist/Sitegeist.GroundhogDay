import { RRule } from 'rrule'
import { RRuleEndType } from '../types'

export const updateRRuleEndOptions = (rrule: RRule, endType: RRuleEndType): RRule => {
    const baseOptions = { ...rrule.options }

    switch (endType) {
        case 'never':
            return new RRule({ ...baseOptions, count: undefined, until: undefined })
        case 'until':
            return new RRule({ ...baseOptions, count: undefined, until: new Date() })
        case 'count':
            return new RRule({ ...baseOptions, until: undefined, count: 1 })
        default:
            return rrule
    }
}
