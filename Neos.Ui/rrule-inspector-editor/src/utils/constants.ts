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
    { label: 'Mo', value: 0 },
    { label: 'Tu', value: 1 },
    { label: 'We', value: 2 },
    { label: 'Th', value: 3 },
    { label: 'Fr', value: 4 },
    { label: 'Sa', value: 5 },
    { label: 'Su', value: 6 }
]

export const BYSETPOS_OPTIONS = [
    { label: 'First', value: 1 },
    { label: 'Second', value: 2 },
    { label: 'Third', value: 3 },
    { label: 'Fourth', value: 4 },
    { label: 'Last', value: -1 }
]

export const BYDAY_OPTIONS = [
    { label: 'Monday', value: 0 },
    { label: 'Tuesday', value: 1 },
    { label: 'Wednsday', value: 2 },
    { label: 'Thursday', value: 3 },
    { label: 'Friday', value: 4 },
    { label: 'Saturday', value: 5 },
    { label: 'Sunday', value: 6 },
    { label: 'Day', value: [0, 1, 2, 3, 4, 5, 6] },
    { label: 'Weekday', value: [0, 1, 2, 3, 4] },
    { label: 'Weekendday', value: [5,6] }
]

export const MONTH_OPTIONS = [
    { label: 'Jan', value: 1 },
    { label: 'Feb', value: 2 },
    { label: 'Mar', value: 3 },
    { label: 'Apr', value: 4 },
    { label: 'May', value: 5 },
    { label: 'Jun', value: 6 },
    { label: 'Jul', value: 7 },
    { label: 'Aug', value: 8 },
    { label: 'Sep', value: 9 },
    { label: 'Oct', value: 10 },
    { label: 'Nov', value: 11 },
    { label: 'Dec', value: 12 },
];