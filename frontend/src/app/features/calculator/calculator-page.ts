import {
  Component,
  DestroyRef,
  computed,
  inject,
  OnInit,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import {
  AbstractControl,
  FormBuilder,
  ReactiveFormsModule,
  ValidationErrors,
  ValidatorFn,
  Validators,
} from '@angular/forms';
import { finalize } from 'rxjs';
import {
  ApiClientError,
  CalculationRequestDto,
  CalculationResultDto,
  DeliveryChannelDto,
  OrderCostCurrency,
  PlatformCode,
  PlatformDto,
} from '../../core/models/api.models';
import { LocaleService } from '../../core/i18n/locale.service';
import type { CalculatorMessages } from '../../core/i18n/calculator-messages';
import { CalculatorApiService } from '../../core/services/calculator-api.service';
import { CALCULATOR_PLATFORMS } from '../../core/constants/platforms';
import { SiteHeader } from '../../shared/components/site-header/site-header';
import { ResultCard } from './result-card';

/** Клиентские ошибки формы: текст берётся из текущего языка, а не из снимка. */
type LocalizedErrorKey = keyof Pick<
  CalculatorMessages,
  'fixHighlightedFields' | 'networkError' | 'unexpectedError'
>;

function positiveNumberString(): ValidatorFn {
  return (control: AbstractControl): ValidationErrors | null => {
    const raw = control.value;
    if (raw === null || raw === undefined || String(raw).trim() === '') {
      return null;
    }

    const value = String(raw).trim();
    if (!/^\d+(\.\d+)?$/.test(value)) {
      return { numberFormat: true };
    }

    if (value === '0' || /^0+(\.0+)?$/.test(value)) {
      return { positive: true };
    }

    return null;
  };
}

@Component({
  selector: 'app-calculator-page',
  imports: [ReactiveFormsModule, SiteHeader, ResultCard],
  templateUrl: './calculator-page.html',
  styleUrl: './calculator-page.scss',
})
export class CalculatorPage implements OnInit {
  private readonly api = inject(CalculatorApiService);
  private readonly fb = inject(FormBuilder);
  private readonly destroyRef = inject(DestroyRef);
  protected readonly locale = inject(LocaleService);

  /** Вшито в бандл: совпадает с backend enum, без HTTP. */
  readonly platforms = signal<PlatformDto[]>([...CALCULATOR_PLATFORMS]);
  /** Все каналы из bootstrap; фильтр по platform на клиенте. */
  private readonly allChannels = signal<DeliveryChannelDto[]>([]);
  readonly channels = signal<DeliveryChannelDto[]>([]);
  readonly bootstrapLoading = signal(true);
  readonly submitting = signal(false);
  private readonly bootstrapErrorKey = signal<LocalizedErrorKey | null>(null);
  private readonly bootstrapErrorRaw = signal<string | null>(null);
  private readonly formErrorKey = signal<LocalizedErrorKey | null>(null);
  private readonly formErrorRaw = signal<string | null>(null);
  readonly fieldErrors = signal<Record<string, string[]>>({});
  readonly result = signal<CalculationResultDto | null>(null);
  readonly showYandexDimensions = signal(false);

  /** Сообщение bootstrap с учётом текущего языка. */
  readonly bootstrapError = computed(() => this.resolveError(this.bootstrapErrorKey(), this.bootstrapErrorRaw()));

  /** Сообщение формы с учётом текущего языка. */
  readonly formError = computed(() => this.resolveError(this.formErrorKey(), this.formErrorRaw()));

  readonly form = this.fb.nonNullable.group({
    platform: this.fb.nonNullable.control<PlatformCode | ''>('', {
      validators: [Validators.required],
    }),
    delivery_channel_code: this.fb.nonNullable.control('', {
      validators: [Validators.required],
    }),
    physical_weight_grams: this.fb.nonNullable.control('', {
      validators: [Validators.required, positiveNumberString()],
    }),
    length_cm: this.fb.nonNullable.control('', {
      validators: [positiveNumberString()],
    }),
    width_cm: this.fb.nonNullable.control('', {
      validators: [positiveNumberString()],
    }),
    height_cm: this.fb.nonNullable.control('', {
      validators: [positiveNumberString()],
    }),
    order_cost: this.fb.nonNullable.control('', {
      validators: [positiveNumberString()],
    }),
    order_cost_currency: this.fb.nonNullable.control<OrderCostCurrency>('CNY'),
  });

  readonly selectedPlatform = signal<PlatformCode | ''>('');

  readonly isOzon = computed(() => this.selectedPlatform() === 'ozon');
  readonly isYandex = computed(() => this.selectedPlatform() === 'yandex_market');
  readonly showDimensions = computed(
    () => this.isOzon() || (this.isYandex() && this.showYandexDimensions()),
  );

  ngOnInit(): void {
    this.api
      .getBootstrap()
      .pipe(
        takeUntilDestroyed(this.destroyRef),
        finalize(() => this.bootstrapLoading.set(false)),
      )
      .subscribe({
        next: (bootstrap) => {
          this.allChannels.set(bootstrap.delivery_channels);
          this.clearBootstrapError();
          this.applyChannelFilter(this.form.controls.platform.value);
        },
        error: (error: unknown) => {
          this.setBootstrapFromUnknown(error);
        },
      });

    this.form.controls.platform.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((platform) => {
        this.onPlatformChanged(platform);
      });
  }

  toggleYandexDimensions(): void {
    this.showYandexDimensions.update((value) => !value);
  }

  onSubmit(): void {
    this.clearFormError();
    this.fieldErrors.set({});
    this.applyPlatformValidators();
    this.form.markAllAsTouched();

    if (this.form.invalid) {
      this.setFormErrorKey('fixHighlightedFields');
      return;
    }

    const payload = this.buildPayload();
    if (payload === null) {
      this.setFormErrorKey('fixHighlightedFields');
      return;
    }

    this.submitting.set(true);
    this.result.set(null);

    this.api
      .calculate(payload)
      .pipe(
        takeUntilDestroyed(this.destroyRef),
        finalize(() => this.submitting.set(false)),
      )
      .subscribe({
        next: (response) => {
          this.result.set(response);
          this.clearFormError();
          this.fieldErrors.set({});
        },
        error: (error: unknown) => {
          if (error instanceof ApiClientError) {
            this.fieldErrors.set(error.fieldErrors);
          }

          this.setFormFromUnknown(error);
        },
      });
  }

  fieldError(controlName: string): string | null {
    const server = this.fieldErrors()[controlName];
    if (server && server.length > 0) {
      return server[0] ?? null;
    }

    const control = this.form.get(controlName);
    if (!control || !control.touched || !control.errors) {
      return null;
    }

    if (control.errors['required']) {
      return this.locale.messages().fieldRequired;
    }
    if (control.errors['numberFormat']) {
      return this.locale.messages().fieldNumberFormat;
    }
    if (control.errors['positive']) {
      return this.locale.messages().fieldPositive;
    }

    return this.locale.messages().fieldInvalid;
  }

  private onPlatformChanged(platform: PlatformCode | ''): void {
    this.selectedPlatform.set(platform);
    this.result.set(null);
    this.form.controls.delivery_channel_code.setValue('');
    this.showYandexDimensions.set(false);
    this.applyPlatformValidators();
    this.applyChannelFilter(platform);
  }

  private applyChannelFilter(platform: PlatformCode | ''): void {
    if (platform === '') {
      this.channels.set([]);
      return;
    }

    this.channels.set(
      this.allChannels().filter((channel) => channel.platform === platform),
    );
  }

  private applyPlatformValidators(): void {
    const length = this.form.controls.length_cm;
    const width = this.form.controls.width_cm;
    const height = this.form.controls.height_cm;
    const orderCost = this.form.controls.order_cost;
    const currency = this.form.controls.order_cost_currency;

    if (this.isOzon()) {
      length.setValidators([Validators.required, positiveNumberString()]);
      width.setValidators([Validators.required, positiveNumberString()]);
      height.setValidators([Validators.required, positiveNumberString()]);
      orderCost.setValidators([Validators.required, positiveNumberString()]);
      currency.setValidators([Validators.required]);
    } else {
      length.setValidators([positiveNumberString()]);
      width.setValidators([positiveNumberString()]);
      height.setValidators([positiveNumberString()]);
      orderCost.setValidators([positiveNumberString()]);
      currency.clearValidators();
    }

    length.updateValueAndValidity({ emitEvent: false });
    width.updateValueAndValidity({ emitEvent: false });
    height.updateValueAndValidity({ emitEvent: false });
    orderCost.updateValueAndValidity({ emitEvent: false });
    currency.updateValueAndValidity({ emitEvent: false });
  }

  private buildPayload(): CalculationRequestDto | null {
    const value = this.form.getRawValue();
    if (value.platform === '') {
      return null;
    }

    const payload: CalculationRequestDto = {
      platform: value.platform,
      delivery_channel_code: value.delivery_channel_code.trim(),
      physical_weight_grams: value.physical_weight_grams.trim(),
    };

    if (this.isOzon()) {
      payload.length_cm = value.length_cm.trim();
      payload.width_cm = value.width_cm.trim();
      payload.height_cm = value.height_cm.trim();
      payload.order_cost = value.order_cost.trim();
      payload.order_cost_currency = value.order_cost_currency;
      return payload;
    }

    if (this.showYandexDimensions()) {
      if (value.length_cm.trim() !== '') {
        payload.length_cm = value.length_cm.trim();
      }
      if (value.width_cm.trim() !== '') {
        payload.width_cm = value.width_cm.trim();
      }
      if (value.height_cm.trim() !== '') {
        payload.height_cm = value.height_cm.trim();
      }
    }

    if (value.order_cost.trim() !== '') {
      payload.order_cost = value.order_cost.trim();
      payload.order_cost_currency = value.order_cost_currency;
    }

    return payload;
  }

  private resolveError(key: LocalizedErrorKey | null, raw: string | null): string | null {
    if (key !== null) {
      return this.locale.messages()[key];
    }

    return raw;
  }

  private clearBootstrapError(): void {
    this.bootstrapErrorKey.set(null);
    this.bootstrapErrorRaw.set(null);
  }

  private clearFormError(): void {
    this.formErrorKey.set(null);
    this.formErrorRaw.set(null);
  }

  private setFormErrorKey(key: LocalizedErrorKey): void {
    this.formErrorRaw.set(null);
    this.formErrorKey.set(key);
  }

  private setBootstrapFromUnknown(error: unknown): void {
    this.applyUnknownError(error, (key) => {
      this.bootstrapErrorRaw.set(null);
      this.bootstrapErrorKey.set(key);
    }, (message) => {
      this.bootstrapErrorKey.set(null);
      this.bootstrapErrorRaw.set(message);
    });
  }

  private setFormFromUnknown(error: unknown): void {
    this.applyUnknownError(error, (key) => this.setFormErrorKey(key), (message) => {
      this.formErrorKey.set(null);
      this.formErrorRaw.set(message);
    });
  }

  private applyUnknownError(
    error: unknown,
    setKey: (key: LocalizedErrorKey) => void,
    setRaw: (message: string) => void,
  ): void {
    if (error instanceof ApiClientError) {
      if (error.code === 'network_error') {
        setKey('networkError');
        return;
      }

      setRaw(error.message);
      return;
    }

    if (error instanceof Error) {
      setRaw(error.message);
      return;
    }

    setKey('unexpectedError');
  }
}
