import { Injectable } from '@angular/core';
import { Observable, of, shareReplay, tap } from 'rxjs';

interface StoredEntry {
  storedAt: number;
  value: unknown;
}

interface MemoryEntry {
  storedAt: number;
  value: unknown;
}

/**
 * Кэш публичных GET калькулятора в памяти и localStorage.
 * TTL ограничивает устаревание у гостя; admin-мутации вызывают clear().
 */
@Injectable({ providedIn: 'root' })
export class CalculatorQueryCache {
  static readonly STORAGE_PREFIX = 'atc.calculator.query.';
  /** 15 минут: компромисс между свежестью тарифов и числом запросов. */
  static readonly TTL_MS = 15 * 60 * 1000;

  private readonly values = new Map<string, MemoryEntry>();
  private readonly inflight = new Map<string, Observable<unknown>>();

  peek<T>(key: string): T | undefined {
    return this.readFresh(key) as T | undefined;
  }

  has(key: string): boolean {
    return this.readFresh(key) !== undefined;
  }

  set<T>(key: string, value: T): void {
    const storedAt = Date.now();
    this.values.set(key, { storedAt, value });
    this.inflight.delete(key);
    this.writeStorage(key, value, storedAt);
  }

  /**
   * Возвращает кэш (memory/localStorage), иначе один in-flight loader.
   * Ошибка не кэшируется.
   */
  getOrLoad<T>(key: string, loader: () => Observable<T>): Observable<T> {
    const cached = this.readFresh(key);
    if (cached !== undefined) {
      return of(cached as T);
    }

    const pending = this.inflight.get(key);
    if (pending) {
      return pending as Observable<T>;
    }

    const request$ = loader().pipe(
      tap({
        next: (value) => {
          this.set(key, value);
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

  clear(): void {
    this.values.clear();
    this.inflight.clear();
    this.clearStorage();
  }

  private readFresh(key: string): unknown | undefined {
    const memory = this.values.get(key);
    if (memory !== undefined) {
      if (Date.now() - memory.storedAt <= CalculatorQueryCache.TTL_MS) {
        return memory.value;
      }
      this.values.delete(key);
      this.removeStorage(key);
    }

    return this.hydrateFromStorage(key);
  }

  private hydrateFromStorage(key: string): unknown | undefined {
    if (typeof localStorage === 'undefined') {
      return undefined;
    }

    try {
      const raw = localStorage.getItem(CalculatorQueryCache.STORAGE_PREFIX + key);
      if (raw === null) {
        return undefined;
      }

      const parsed = JSON.parse(raw) as StoredEntry;
      if (
        typeof parsed !== 'object' ||
        parsed === null ||
        typeof parsed.storedAt !== 'number' ||
        !('value' in parsed)
      ) {
        this.removeStorage(key);
        return undefined;
      }

      if (Date.now() - parsed.storedAt > CalculatorQueryCache.TTL_MS) {
        this.removeStorage(key);
        return undefined;
      }

      this.values.set(key, { storedAt: parsed.storedAt, value: parsed.value });
      return parsed.value;
    } catch {
      this.removeStorage(key);
      return undefined;
    }
  }

  private writeStorage(key: string, value: unknown, storedAt: number): void {
    if (typeof localStorage === 'undefined') {
      return;
    }

    try {
      const entry: StoredEntry = { storedAt, value };
      localStorage.setItem(
        CalculatorQueryCache.STORAGE_PREFIX + key,
        JSON.stringify(entry),
      );
    } catch {
      // Quota / private mode: оставляем только memory-кэш.
    }
  }

  private removeStorage(key: string): void {
    if (typeof localStorage === 'undefined') {
      return;
    }
    localStorage.removeItem(CalculatorQueryCache.STORAGE_PREFIX + key);
  }

  private clearStorage(): void {
    if (typeof localStorage === 'undefined') {
      return;
    }

    const keys: string[] = [];
    for (let i = 0; i < localStorage.length; i += 1) {
      const key = localStorage.key(i);
      if (key !== null && key.startsWith(CalculatorQueryCache.STORAGE_PREFIX)) {
        keys.push(key);
      }
    }
    for (const key of keys) {
      localStorage.removeItem(key);
    }
  }
}
