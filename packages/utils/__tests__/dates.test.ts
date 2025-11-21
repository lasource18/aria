import { expect, test, describe } from 'bun:test';
import { formatEventDate, formatShortDate } from '../src/dates';

describe('formatEventDate', () => {
  test('formats date with time in Africa/Abidjan timezone', () => {
    const result = formatEventDate('2025-11-20T14:30:00Z', 'Africa/Abidjan');
    // Result should be in format like "Nov 20, 2025, 2:30 PM" (depends on locale)
    expect(result).toContain('2025');
    expect(result).toContain('20');
  });

  test('accepts Date objects', () => {
    const date = new Date('2025-11-20T14:30:00Z');
    const result = formatEventDate(date, 'Africa/Abidjan');
    expect(result).toContain('2025');
  });

  test('uses default Africa/Abidjan timezone', () => {
    const result = formatEventDate('2025-11-20T14:30:00Z');
    expect(result).toContain('2025');
  });
});

describe('formatShortDate', () => {
  test('formats date without time', () => {
    const result = formatShortDate('2025-11-20T14:30:00Z', 'Africa/Abidjan');
    // Result should be in format like "Nov 20, 2025"
    expect(result).toContain('2025');
    expect(result).toContain('20');
    // Should not contain time
    expect(result).not.toContain(':');
  });
});
