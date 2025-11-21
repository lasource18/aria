import { formatInTimeZone, toZonedTime } from 'date-fns-tz';

/**
 * Convert a date to Africa/Abidjan timezone
 * @example toAfricaAbidjan(new Date('2025-11-20T14:30:00Z'))
 */
export function toAfricaAbidjan(date: Date): Date {
  return toZonedTime(date, 'Africa/Abidjan');
}

/**
 * Format event date in Africa/Abidjan timezone (or custom timezone)
 * @example formatEventDate("2025-11-20T14:30:00Z", "Africa/Abidjan") // "Nov 20, 2025, 2:30 PM"
 */
export function formatEventDate(
  date: string | Date,
  timezone: string = 'Africa/Abidjan'
): string {
  const dateObj = typeof date === 'string' ? new Date(date) : date;
  return formatInTimeZone(dateObj, timezone, 'PPpp'); // "Apr 29, 2023, 9:00 AM"
}

/**
 * Format a short date (no time)
 * @example formatShortDate("2025-11-20T14:30:00Z", "Africa/Abidjan") // "Nov 20, 2025"
 */
export function formatShortDate(
  date: string | Date,
  timezone: string = 'Africa/Abidjan'
): string {
  const dateObj = typeof date === 'string' ? new Date(date) : date;
  return formatInTimeZone(dateObj, timezone, 'PP'); // "Apr 29, 2023"
}
