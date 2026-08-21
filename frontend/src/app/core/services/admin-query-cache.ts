import { Injectable } from '@angular/core';
import { Observable, of, shareReplay, tap } from 'rxjs';

/**
 * In-memory кэш admin GET-запросов с дедупликацией in-flight.
 * Мутации должны инвалидировать ключи через invalidate / clear.
 */
@Injectable({ providedIn: 'root' })
export class AdminQueryCache {
  private readonly values = new Map<string, unknown>();
  private readonly inflight = new Map<string, Observable<unknown>>();

  /** Синхронно читает уже закэшированное значение. */
  peek<T>(key: string): T | undefined {
    return this.values.get(key) as T | undefined;
  }

  has(key: string): boolean {
    return this.values.has(key);
  }

  set<T>(key: string, value: T): void {
    this.values.set(key, value);
    this.inflight.delete(key);
  }

  /**
   * Возвращает кэш, иначе запускает loader один раз и шарит результат.
   * Ошибка не кэшируется.
   */
  getOrLoad<T>(key: string, loader: () => Observable<T>): Observable<T> {
    if (this.values.has(key)) {
      return of(this.values.get(key) as T);
    }

    const pending = this.inflight.get(key);
    if (pending) {
      return pending as Observable<T>;
    }

    const request$ = loader().pipe(
      tap({
        next: (value) => {
          this.values.set(key, value);
          this.inflight.delete(key);
        },
        error: () => {
          this.inflight.delete(key);
        },
      }),
      shareReplay({ bufferSize: 1, refCount: false }),
    );

    this.inflight.set(key, request$);
    return request$;
  }

  /** Удаляет ключи с точным совпадением или префиксом `prefix:`. */
  invalidate(keysOrPrefixes: string | readonly string[]): void {
    const items = typeof keysOrPrefixes === 'string' ? [keysOrPrefixes] : keysOrPrefixes;

    for (const item of items) {
      this.values.delete(item);
      this.inflight.delete(item);

      for (const key of [...this.values.keys()]) {
        if (key.startsWith(`${item}:`) || key.startsWith(`${item}?`)) {
          this.values.delete(key);
        }
      }
      for (const key of [...this.inflight.keys()]) {
        if (key.startsWith(`${item}:`) || key.startsWith(`${item}?`)) {
          this.inflight.delete(key);
        }
      }
    }
  }

  clear(): void {
    this.values.clear();
    this.inflight.clear();
  }
}
