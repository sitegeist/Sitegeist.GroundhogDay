import { Frequency } from "rrule"

export const END_TYPE_OPTIONS = [
    { label: 'Never', value: 'never', icon: 'infinity' },
    { label: 'Until Date', value: 'until', icon: 'calendar-week' },
    { label: 'Occurences', value: 'count', icon: 'hashtag' }
]

export const REPEAT_TYPE_OPTIONS = [
    { label: 'Yearly', value: Frequency.YEARLY, icon: 'calendar' },
    { label: 'Monthly', value: Frequency.MONTHLY, icon: 'calendar-alt' },
    { label: 'Weekly', value: Frequency.WEEKLY, icon: 'calendar-week' },
    { label: 'Daily', value: Frequency.DAILY, icon: 'calendar-day' },
    { label: 'Hourly', value: Frequency.HOURLY, icon: 'clock' }
]

