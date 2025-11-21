import { expect, test, describe } from 'bun:test';
import { formatXOF, parseXOF } from '../src/currency';

describe('formatXOF', () => {
  test('formats with space separator', () => {
    expect(formatXOF(5000)).toBe('5 000 XOF');
    expect(formatXOF(1500000)).toBe('1 500 000 XOF');
  });

  test('handles zero amount', () => {
    expect(formatXOF(0)).toBe('0 XOF');
  });

  test('handles single digit', () => {
    expect(formatXOF(5)).toBe('5 XOF');
  });

  test('handles two digits', () => {
    expect(formatXOF(50)).toBe('50 XOF');
  });

  test('handles three digits (no separator)', () => {
    expect(formatXOF(500)).toBe('500 XOF');
  });

  test('handles four digits', () => {
    expect(formatXOF(5000)).toBe('5 000 XOF');
  });

  test('handles large amounts', () => {
    expect(formatXOF(1000000)).toBe('1 000 000 XOF');
    expect(formatXOF(999999999)).toBe('999 999 999 XOF');
  });

  test('rounds decimal amounts', () => {
    expect(formatXOF(5000.4)).toBe('5 000 XOF');
    expect(formatXOF(5000.6)).toBe('5 001 XOF');
  });

  test('handles negative amounts', () => {
    expect(formatXOF(-5000)).toBe('-5 000 XOF');
  });
});

describe('parseXOF', () => {
  test('removes formatting', () => {
    expect(parseXOF('5 000 XOF')).toBe(5000);
    expect(parseXOF('1 500 000 XOF')).toBe(1500000);
  });

  test('handles zero', () => {
    expect(parseXOF('0 XOF')).toBe(0);
  });

  test('handles amounts without spaces', () => {
    expect(parseXOF('5000 XOF')).toBe(5000);
    expect(parseXOF('5000XOF')).toBe(5000);
  });

  test('handles plain numbers', () => {
    expect(parseXOF('5000')).toBe(5000);
  });

  test('handles empty or invalid input', () => {
    expect(parseXOF('')).toBe(0);
    expect(parseXOF('XOF')).toBe(0);
    expect(parseXOF('abc')).toBe(0);
  });
});
