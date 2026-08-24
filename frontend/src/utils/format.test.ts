import { describe, expect, it } from 'vitest';
import { formatCount, formatDate } from './format';

describe('formatCount', () => {
  it('formats numbers with ru-RU grouping', () => {
    expect(formatCount(10234)).toBe('10\u00A0234');
  });

  it('returns zero string for undefined', () => {
    expect(formatCount(undefined)).toBe('0');
  });
});

describe('formatDate', () => {
  it('shows "just now" for recent dates', () => {
    const now = new Date().toISOString();
    expect(formatDate(now)).toBe('только что');
  });

  it('shows hours for same-day older dates', () => {
    const threeHoursAgo = new Date(Date.now() - 3 * 3600_000).toISOString();
    expect(formatDate(threeHoursAgo)).toBe('3 ч.');
  });

  it('shows absolute date for old dates', () => {
    const old = new Date('2024-03-05T10:00:00Z');
    expect(formatDate(old.toISOString())).toMatch(/5 марта/);
  });

  it('returns empty string for null', () => {
    expect(formatDate(null)).toBe('');
  });
});
