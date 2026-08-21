import { describe, expect, it } from 'vitest';
import { formatDecimalString, formatMoney } from './money-display';

describe('formatDecimalString', () => {
  it('formats integer grouping without float conversion', () => {
    expect(formatDecimalString('1234.5600')).toBe('1,234.5600');
  });

  it('keeps trailing fractional digits from API string', () => {
    expect(formatDecimalString('0.085000')).toBe('0.085000');
  });

  it('renders placeholder for empty values', () => {
    expect(formatDecimalString(null)).toBe('—');
    expect(formatDecimalString(undefined)).toBe('—');
  });
});

describe('formatMoney', () => {
  it('appends currency code', () => {
    expect(formatMoney('761.80', 'RUB')).toBe('761.80 RUB');
  });
});
