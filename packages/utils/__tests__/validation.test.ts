import { expect, test, describe } from 'bun:test';
import { isValidE164Phone, isValidEmail, isValidSlug } from '../src/validation';

describe('isValidE164Phone', () => {
  test('validates Côte d\'Ivoire numbers', () => {
    expect(isValidE164Phone('+2250707123456')).toBe(true);
    expect(isValidE164Phone('+2250101234567')).toBe(true);
  });

  test('validates French numbers', () => {
    expect(isValidE164Phone('+33612345678')).toBe(true);
  });

  test('validates US numbers', () => {
    expect(isValidE164Phone('+12025551234')).toBe(true);
  });

  test('rejects numbers without country code', () => {
    expect(isValidE164Phone('0707123456')).toBe(false);
  });

  test('rejects numbers without plus sign', () => {
    expect(isValidE164Phone('2250707123456')).toBe(false);
  });

  test('rejects numbers that are too short', () => {
    expect(isValidE164Phone('+225070712')).toBe(false);
  });

  test('rejects numbers that are too long', () => {
    expect(isValidE164Phone('+2250707123456789012')).toBe(false);
  });

  test('rejects invalid formats', () => {
    expect(isValidE164Phone('')).toBe(false);
    expect(isValidE164Phone('abc')).toBe(false);
    expect(isValidE164Phone('+225 07 07 12 34 56')).toBe(false);
  });
});

describe('isValidEmail', () => {
  test('validates correct email formats', () => {
    expect(isValidEmail('user@example.com')).toBe(true);
    expect(isValidEmail('test.user@example.co.uk')).toBe(true);
    expect(isValidEmail('user+tag@example.com')).toBe(true);
  });

  test('rejects invalid email formats', () => {
    expect(isValidEmail('invalid.email')).toBe(false);
    expect(isValidEmail('user@')).toBe(false);
    expect(isValidEmail('@example.com')).toBe(false);
    expect(isValidEmail('user @example.com')).toBe(false);
    expect(isValidEmail('')).toBe(false);
  });
});

describe('isValidSlug', () => {
  test('validates correct slug formats', () => {
    expect(isValidSlug('my-event-slug')).toBe(true);
    expect(isValidSlug('event123')).toBe(true);
    expect(isValidSlug('a')).toBe(true);
    expect(isValidSlug('event-2025-concert')).toBe(true);
  });

  test('rejects invalid slug formats', () => {
    expect(isValidSlug('My Event Slug')).toBe(false); // uppercase
    expect(isValidSlug('my_event_slug')).toBe(false); // underscores
    expect(isValidSlug('my event slug')).toBe(false); // spaces
    expect(isValidSlug('my-event-')).toBe(false); // trailing hyphen
    expect(isValidSlug('-my-event')).toBe(false); // leading hyphen
    expect(isValidSlug('')).toBe(false); // empty
  });
});
