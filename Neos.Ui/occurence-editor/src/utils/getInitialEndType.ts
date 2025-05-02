import { RRule } from "rrule"
import { RRuleEndType } from "../types"

export const getInitialEndType = (rrule: RRule): RRuleEndType => {
    if (rrule.options.count != null) return 'count'
    if (rrule.options.until != null) return 'until'
    return 'never'
}
