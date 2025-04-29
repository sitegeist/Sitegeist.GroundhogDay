import { RRule } from "rrule"
import { YearlyFrequencyType } from "../types"

export const getInitialYearFrequencyType = (rrule: RRule): YearlyFrequencyType => {
    if (rrule.options.byweekday != null || rrule.options.bysetpos) return YearlyFrequencyType.BYSETPOS; 
    return YearlyFrequencyType.BYMONTHS;
}