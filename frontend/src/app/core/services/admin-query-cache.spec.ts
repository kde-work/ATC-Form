import { describe, expect, it } from 'vitest';
import { firstValueFrom, of, throwError } from 'rxjs';
import { AdminQueryCache } from './admin-query-cache';

describe('AdminQueryCache', () => {
  it('возвращает закэшированное значение без повторного loader', async () => {
    const cache = new AdminQueryCache();
    let loads = 0;

    const first = await firstValueFrom(
      cache.getOrLoad('settings', () => {
        loads += 1;
        return of({ rate: '0.1' });
      }),
    );
    const second = await firstValueFrom(
      cache.getOrLoad('settings', () => {
        loads += 1;
        return of({ rate: '0.2' });
      }),
    );

    expect(first).toEqual({ rate: '0.1' });
    expect(second).toEqual({ rate: '0.1' });
    expect(loads).toBe(1);
  });

  it('не кэширует ошибку и позволяет повторить запрос', async () => {
    const cache = new AdminQueryCache();
    let loads = 0;

    await expect(
      firstValueFrom(
        cache.getOrLoad('settings', () => {
          loads += 1;
          return throwError(() => new Error('fail'));
        }),
      ),
    ).rejects.toThrow('fail');

    const value = await firstValueFrom(
      cache.getOrLoad('settings', () => {
        loads += 1;
        return of({ ok: true });
      }),
    );

    expect(value).toEqual({ ok: true });
    expect(loads).toBe(2);
  });

  it('invalidate по префиксу удаляет связанные ключи', () => {
    const cache = new AdminQueryCache();
    cache.set('imports?status=&page=1&per_page=15', { data: [] });
    cache.set('imports:12', { id: 12 });
    cache.set('settings', { rate: '1' });

    cache.invalidate('imports');

    expect(cache.has('imports?status=&page=1&per_page=15')).toBe(false);
    expect(cache.has('imports:12')).toBe(false);
    expect(cache.has('settings')).toBe(true);
  });
});
