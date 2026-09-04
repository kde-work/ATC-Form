import { TestBed } from '@angular/core/testing';
import { DEFAULT_LOCALE, LOCALE_STORAGE_KEY } from './calculator-messages';
import { LocaleService } from './locale.service';

describe('LocaleService', () => {
  beforeEach(() => {
    localStorage.removeItem(LOCALE_STORAGE_KEY);
    TestBed.configureTestingModule({});
  });

  afterEach(() => {
    localStorage.removeItem(LOCALE_STORAGE_KEY);
  });

  it('по умолчанию использует китайский и пишет выбор в localStorage', () => {
    const service = TestBed.inject(LocaleService);

    expect(service.locale()).toBe(DEFAULT_LOCALE);
    expect(service.messages().calculate).toBe('开始计算');

    service.setLocale('en');

    expect(service.locale()).toBe('en');
    expect(localStorage.getItem(LOCALE_STORAGE_KEY)).toBe('en');
    expect(service.messages().calculate).toBe('Calculate');
  });

  it('восстанавливает сохранённый язык из localStorage', () => {
    localStorage.setItem(LOCALE_STORAGE_KEY, 'en');
    const service = TestBed.inject(LocaleService);

    expect(service.locale()).toBe('en');
    expect(service.messages().siteTitle).toBe('Delivery cost calculator');
  });
});
