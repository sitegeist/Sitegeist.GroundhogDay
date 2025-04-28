import { Frequency } from "rrule"

export const END_TYPE_OPTIONS = [
    { label: 'Never', value: 'never', icon: 'infinity' },
    { label: 'Until Date', value: 'until', icon: 'calendar-week' },
    { label: 'Occurences', value: 'count', icon: 'hashtag' }
]

export const FREQ_TYPE_OPTIONS = [
    { label: 'Yearly', value: Frequency.YEARLY, icon: 'calendar' },
    { label: 'Monthly', value: Frequency.MONTHLY, icon: 'calendar-alt' },
    { label: 'Weekly', value: Frequency.WEEKLY, icon: 'calendar-week' },
    { label: 'Daily', value: Frequency.DAILY, icon: 'calendar-day' },
    { label: 'Hourly', value: Frequency.HOURLY, icon: 'clock' }
]

export const WEEKDAY_OPTIONS = [
    { label: 'Mo', value: 0, icon: 'calendar-day' },
    { label: 'Tu', value: 1, icon: 'calendar-day' },
    { label: 'We', value: 2, icon: 'calendar-day' },
    { label: 'Th', value: 3, icon: 'calendar-day' },
    { label: 'Fr', value: 4, icon: 'calendar-day' },
    { label: 'Sa', value: 5, icon: 'calendar-day' },
    { label: 'Su', value: 6, icon: 'calendar-day' }
]