import { RRule } from "rrule"

export const getBaseRRule = () => {
    return new RRule({
        freq: RRule.DAILY,
        dtstart: new Date(),
    })
}