/**
 * Локализованное отображение денежной/десятичной строки API без Number()/float.
 * Целая часть форматируется через Intl, дробная берётся из исходной строки.
 */
export function formatDecimalString(
  value: string | null | undefined,
  locale = 'en-US',
): string {
  if (value === null || value === undefined || value === '') {
    return '—';
  }

  const match = /^(-?)(\d+)(?:\.(\d+))?$/.exec(value.trim());
  if (!match) {
    return value;
  }

  const sign = match[1] ?? '';
  const intPart = match[2] ?? '0';
  const fracPart = match[3];

  const groupedInt = new Intl.NumberFormat(locale, {
    useGrouping: true,
    maximumFractionDigits: 0,
  }).format(BigInt(intPart));

  if (fracPart === undefined) {
    return `${sign}${groupedInt}`;
  }

  return `${sign}${groupedInt}.${fracPart}`;
}

/** Деньги с кодом валюты: "1,234.56 CNY". */
export function formatMoney(
  value: string | null | undefined,
  currency: string | null | undefined,
  locale = 'en-US',
): string {
  const amount = formatDecimalString(value, locale);
  if (amount === '—' || !currency) {
    return amount;
  }

  return `${amount} ${currency}`;
}
