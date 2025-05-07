import { format } from 'date-fns'
import { COMMIT_DATE_FORMAT } from './constants';

export const normalizeDates = (dates?: (Date | null)[]): string[] | undefined => {
    if (!dates || dates.length === 0) return undefined;
    const validDates = dates.filter((date): date is Date => date instanceof Date);
    return validDates.length > 0 ? validDates.map(date => format(date, COMMIT_DATE_FORMAT)) : undefined;
};
