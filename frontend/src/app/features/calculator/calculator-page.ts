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
import { CalculatorApiService } from '../../core/services/calculator-api.service';
import { SiteHeader } from '../../shared/components/site-header/site-header';
import { ResultCard } from './result-card';

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

  readonly platforms = signal<PlatformDto[]>([]);
  /** Все каналы из bootstrap; фильтр по platform на клиенте. */
  private readonly allChannels = signal<DeliveryChannelDto[]>([]);
  readonly channels = signal<DeliveryChannelDto[]>([]);
  readonly bootstrapLoading = signal(true);
  readonly submitting = signal(false);
  readonly bootstrapError = signal<string | null>(null);
  readonly formError = signal<string | null>(null);
  readonly fieldErrors = signal<Record<string, string[]>>({});
  readonly result = signal<CalculationResultDto | null>(null);
  readonly showYandexDimensions = signal(false);

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
          this.platforms.set(bootstrap.platforms);
          this.allChannels.set(bootstrap.delivery_channels);
          this.bootstrapError.set(null);
          this.applyChannelFilter(this.form.controls.platform.value);
        },
        error: (error: unknown) => {
          this.bootstrapError.set(this.errorMessage(error));
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
    this.formError.set(null);
    this.fieldErrors.set({});
    this.applyPlatformValidators();
    this.form.markAllAsTouched();

    if (this.form.invalid) {
      this.formError.set('Please fix the highlighted fields before calculating.');
      return;
    }

    const payload = this.buildPayload();
    if (payload === null) {
      this.formError.set('Please fix the highlighted fields before calculating.');
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
          this.formError.set(null);
          this.fieldErrors.set({});
        },
        error: (error: unknown) => {
          if (error instanceof ApiClientError) {
            this.fieldErrors.set(error.fieldErrors);
            this.formError.set(error.message);
            return;
          }

          this.formError.set(this.errorMessage(error));
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
      return 'This field is required.';
    }
    if (control.errors['numberFormat']) {
      return 'Enter a valid decimal number (e.g. 12.5).';
    }
    if (control.errors['positive']) {
      return 'Value must be greater than 0.';
    }

    return 'Invalid value.';
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

  private errorMessage(error: unknown): string {
    if (error instanceof ApiClientError) {
      return error.message;
    }
    if (error instanceof Error) {
      return error.message;
    }

    return 'Unexpected error.';
  }
}
