import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { firstValueFrom, of, throwError } from 'rxjs';
import { CalculatorQueryCache } from './calculator-query-cache';

describe('CalculatorQueryCache', () => {
  beforeEach(() => {
    localStorage.clear();
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-08-22T10:00:00Z'));
  });

  afterEach(() => {
    vi.useRealTimers();
    localStorage.clear();
  });

  it('кэширует в memory и не вызывает loader повторно', async () => {
    const cache = new CalculatorQueryCache();
    let loads = 0;

    const first = await firstValueFrom(
      cache.getOrLoad('bootstrap', () => {
        loads += 1;
        return of({ platforms: [] });
      }),
    );
    const second = await firstValueFrom(
      cache.getOrLoad('bootstrap', () => {
        loads += 1;
        return of({ platforms: ['x'] });
      }),
    );

    expect(first).toEqual({ platforms: [] });
    expect(second).toEqual({ platforms: [] });
    expect(loads).toBe(1);
  });

  it('поднимает значение из localStorage без HTTP', async () => {
    const first = new CalculatorQueryCache();
    await firstValueFrom(first.getOrLoad('bootstrap', () => of({ ok: true })));

    const second = new CalculatorQueryCache();
    let loads = 0;
    const value = await firstValueFrom(
      second.getOrLoad('bootstrap', () => {
        loads += 1;
        return of({ ok: false });
      }),
    );

    expect(value).toEqual({ ok: true });
    expect(loads).toBe(0);
  });

  it('после TTL снова ходит в loader', async () => {
    const cache = new CalculatorQueryCache();
    await firstValueFrom(cache.getOrLoad('bootstrap', () => of({ v: 1 })));

    vi.setSystemTime(Date.now() + CalculatorQueryCache.TTL_MS + 1);

    let loads = 0;
    const value = await firstValueFrom(
      cache.getOrLoad('bootstrap', () => {
        loads += 1;
        return of({ v: 2 });
      }),
    );

    expect(value).toEqual({ v: 2 });
    expect(loads).toBe(1);
  });

  it('не кэширует ошибку', async () => {
    const cache = new CalculatorQueryCache();
    let loads = 0;

    await expect(
      firstValueFrom(
        cache.getOrLoad('bootstrap', () => {
          loads += 1;
          return throwError(() => new Error('fail'));
        }),
      ),
    ).rejects.toThrow('fail');

    const value = await firstValueFrom(
      cache.getOrLoad('bootstrap', () => {
        loads += 1;
        return of({ ok: true });
      }),
    );

    expect(value).toEqual({ ok: true });
    expect(loads).toBe(2);
  });

  it('clear чистит memory и localStorage', async () => {
    const cache = new CalculatorQueryCache();
    await firstValueFrom(cache.getOrLoad('bootstrap', () => of({ a: 1 })));
    expect(localStorage.getItem(CalculatorQueryCache.STORAGE_PREFIX + 'bootstrap')).not.toBeNull();

    cache.clear();
    expect(cache.has('bootstrap')).toBe(false);
    expect(localStorage.getItem(CalculatorQueryCache.STORAGE_PREFIX + 'bootstrap')).toBeNull();
  });
});
