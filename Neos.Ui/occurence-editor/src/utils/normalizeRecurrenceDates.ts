export const normalizeDates = (dates?: (Date | null)[]): Date[] | undefined => {
    if (!dates || dates.length === 0) return undefined;
    const validDates = dates.filter((date): date is Date => date instanceof Date);
    return validDates.length > 0 ? validDates : undefined;
};