import { Frequency } from "rrule"

export type I18nFn = (key: string) => string;

export const ICAL_DATE_FORMAT = "yyyyMMdd'T'HHmmss'Z'"

const BASE = 'Sitegeist.GroundhogDay:NodeTypes.Mixin.Event';

export const getOccurenceMethodOptions = (i18n: I18nFn) => [
    { label: i18n(`${BASE}:end.never`), value: 'never' },
    { label: i18n(`${BASE}:occurence.rrule`), value: 'rrule' }
]

export const getEventEndTypeOptions = (i18n: I18nFn) => [
    { label: i18n(`${BASE}:eventEndType.endDate`), value: 'endDate' },
    { label: i18n(`${BASE}:eventEndType.duration`), value: 'duration' }
];

export const getDurationUnitOptions = (i18n: I18nFn) => [
    { value: 'minute', label: i18n(`${BASE}:unit.minute`) },
    { value: 'hour', label: i18n(`${BASE}:unit.hour`) },
    { value: 'day', label: i18n(`${BASE}:unit.day`) }
];

export const getEndTypeOptions = (i18n: I18nFn) => [
    { label: i18n(`${BASE}:end.never`), value: 'never', icon: 'infinity' },
    { label: i18n(`${BASE}:end.until`), value: 'until', icon: 'calendar-week' },
    { label: i18n(`${BASE}:end.count`), value: 'count', icon: 'hashtag' },
];

export const getFreqTypeOptions = (i18n: I18nFn) => [
    { label: i18n(`${BASE}:freq.yearly`), value: Frequency.YEARLY, icon: 'calendar' },
    { label: i18n(`${BASE}:freq.monthly`), value: Frequency.MONTHLY, icon: 'calendar-alt' },
    { label: i18n(`${BASE}:freq.weekly`), value: Frequency.WEEKLY, icon: 'calendar-week' },
    { label: i18n(`${BASE}:freq.daily`), value: Frequency.DAILY, icon: 'calendar-day' },
    { label: i18n(`${BASE}:freq.hourly`), value: Frequency.HOURLY, icon: 'clock' },
];

export const getWeekdayOptions = (i18n: I18nFn) => [
    { label: i18n(`${BASE}:weekday.mo`), value: 0 },
    { label: i18n(`${BASE}:weekday.tu`), value: 1 },
    { label: i18n(`${BASE}:weekday.we`), value: 2 },
    { label: i18n(`${BASE}:weekday.th`), value: 3 },
    { label: i18n(`${BASE}:weekday.fr`), value: 4 },
    { label: i18n(`${BASE}:weekday.sa`), value: 5 },
    { label: i18n(`${BASE}:weekday.su`), value: 6 },
];

export const getBySetPosOptions = (i18n: I18nFn) => [
    { label: i18n(`${BASE}:bySetPos.first`), value: 1 },
    { label: i18n(`${BASE}:bySetPos.second`), value: 2 },
    { label: i18n(`${BASE}:bySetPos.third`), value: 3 },
    { label: i18n(`${BASE}:bySetPos.fourth`), value: 4 },
    { label: i18n(`${BASE}:bySetPos.last`), value: -1 },
];

export const getMonthOptions = (i18n: I18nFn) => [
    { label: i18n(`${BASE}:month.jan`), value: 1 },
    { label: i18n(`${BASE}:month.feb`), value: 2 },
    { label: i18n(`${BASE}:month.mar`), value: 3 },
    { label: i18n(`${BASE}:month.apr`), value: 4 },
    { label: i18n(`${BASE}:month.may`), value: 5 },
    { label: i18n(`${BASE}:month.jun`), value: 6 },
    { label: i18n(`${BASE}:month.jul`), value: 7 },
    { label: i18n(`${BASE}:month.aug`), value: 8 },
    { label: i18n(`${BASE}:month.sep`), value: 9 },
    { label: i18n(`${BASE}:month.oct`), value: 10 },
    { label: i18n(`${BASE}:month.nov`), value: 11 },
    { label: i18n(`${BASE}:month.dec`), value: 12 },
];
