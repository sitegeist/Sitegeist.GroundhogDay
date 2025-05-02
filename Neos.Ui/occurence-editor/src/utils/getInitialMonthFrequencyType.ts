import { RRule } from "rrule"
import { MonthFrequencyType } from "../types"

export const getInitialMonthFrequencyType = (rrule: RRule): MonthFrequencyType => {
    if (rrule.options.bymonthday != null) return MonthFrequencyType.BYMONTHDAY;
    if (rrule.options.byweekday != null || rrule.options.bysetpos) return MonthFrequencyType.BYSETPOS; 
    return MonthFrequencyType.BYMONTHDAY;
}