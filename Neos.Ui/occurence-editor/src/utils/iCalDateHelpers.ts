import { format, parse } from "date-fns";
import { ICAL_DATE_FORMAT } from "./constants";
import { DurationUnit } from "../types";

export const parseICalDate = (ical: string): Date =>
    parse(ical, ICAL_DATE_FORMAT, new Date())

export const formatICalDate = (date: Date): string =>
    format(date, ICAL_DATE_FORMAT)

export const parseICalDuration = (
    duration?: string | null
): { count?: number; unit?: DurationUnit } => {
    if (!duration) return { count: undefined, unit: undefined };

    const match = duration.match(
    /^P(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?$/
    );

    if (!match) return { count: undefined, unit: undefined };

    const [ , days, hours, minutes ] = match;

    if (days && parseInt(days, 10) > 0) {
    return { count: parseInt(days, 10), unit: 'day' };
    }
    if (hours && parseInt(hours, 10) > 0) {
    return { count: parseInt(hours, 10), unit: 'hour' };
    }
    if (minutes && parseInt(minutes, 10) > 0) {
    return { count: parseInt(minutes, 10), unit: 'minute' };
    }

    return { count: undefined, unit: undefined };
};
  
export const formatICalDuration = (value: number, unit: DurationUnit): string => {
    const days = unit === 'day' ? value : 0;
    const hours = unit === 'hour' ? value : 0;
    const minutes = unit === 'minute' ? value : 0;

    return `P${days}DT${hours}H${minutes}M0S`;
};

export const serializeExdatesToString = (
    dates?: (Date | null)[]
): string | undefined => {
    if (!dates || dates.length === 0) return undefined;
  
    const validDates = dates.filter((date): date is Date => date instanceof Date);
    if (validDates.length === 0) return undefined;
  
    const parts = validDates.map((date) => {
      const formatted = formatICalDate(date);
      return `TZID=UTC:${formatted}`;
    });
  
    return `EXDATE;${parts.join(',')}`;
};

export const deserializeExdatesFromString = (
    exdateString?: string
): (Date | null)[] => {
    if (!exdateString?.startsWith('EXDATE;')) return [];
  
    const raw = exdateString.slice(7);
    const parts = raw.split(',');
  
    const dates = parts.map((part) => {
      const [tzid, dateStr] = part.split(':');
      if (!dateStr) return null;
  
      return parseICalDate(dateStr);
    });
  
    return dates.length > 0 ? dates : [];
};

export const serializeRdatesToString = (
    dates?: (Date | null)[]
): string | undefined => {
    if (!dates || dates.length === 0) return undefined;

    const validDates = dates.filter((date): date is Date => date instanceof Date);
    if (validDates.length === 0) return undefined;

    const parts = validDates.map((date) => {
        const formatted = formatICalDate(date);
        return `TZID=UTC:${formatted}`;
    });

    return `RDATE;${parts.join(',')}`;
};

export const deserializeRdatesFromString = (
    rdateString?: string
): (Date | null)[] => {
    if (!rdateString?.startsWith('RDATE;')) return [];

    const raw = rdateString.slice(6);
    const parts = raw.split(',');

    const dates = parts.map((part) => {
        const [tzid, dateStr] = part.split(':');
        if (!dateStr) return null;

        return parseICalDate(dateStr);
    });

    return dates.length > 0 ? dates : [];
};