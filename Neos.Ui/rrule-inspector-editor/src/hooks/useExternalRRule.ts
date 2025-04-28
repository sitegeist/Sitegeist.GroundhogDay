import { useMemo } from 'react'
import { RRule } from 'rrule'
import { getBaseRRule } from '../utils/geBaseRRule'

export function useExternalRRule(value?: string) {
    return useMemo(() => {
        if (value) {
            try {
                return RRule.fromString(value)
            } catch (error) {
                console.error('Invalid RRule string:', value, error)
            }
        }
        return getBaseRRule()
    }, [value])
}
