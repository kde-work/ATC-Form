import { computed, Injectable, signal } from '@angular/core';
import {
  AppLocale,
  CALCULATOR_MESSAGES,
  CalculatorMessages,
  DEFAULT_LOCALE,
  LOCALE_STORAGE_KEY,
} from './calculator-messages';

/** Язык UI калькулятора: zh по умолчанию, выбор в localStorage. */
@Injectable({ providedIn: 'root' })
export class LocaleService {
  private readonly localeSignal = signal<AppLocale>(this.readStored());

  readonly locale = this.localeSignal.asReadonly();

  /** Текущие строки калькулятора для активного языка. */
  readonly messages = computed<CalculatorMessages>(
    () => CALCULATOR_MESSAGES[this.localeSignal()],
  );

  constructor() {
    this.applyDocumentLang(this.localeSignal());
  }

  setLocale(locale: AppLocale): void {
    if (locale !== 'zh' && locale !== 'en') {
      return;
    }

    try {
      localStorage.setItem(LOCALE_STORAGE_KEY, locale);
    } catch {
      // private mode / недоступен storage: язык всё равно меняем в сессии
    }

    this.localeSignal.set(locale);
    this.applyDocumentLang(locale);
  }

  private readStored(): AppLocale {
    try {
      const value = localStorage.getItem(LOCALE_STORAGE_KEY);
      if (value === 'zh' || value === 'en') {
        return value;
      }
    } catch {
      // ignore
    }

    return DEFAULT_LOCALE;
  }

  private applyDocumentLang(locale: AppLocale): void {
    if (typeof document === 'undefined') {
      return;
    }

    document.documentElement.lang = locale === 'zh' ? 'zh-Hans' : 'en';
  }
}
