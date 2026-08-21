import { Component, inject, OnInit, signal } from '@angular/core';
import {
  AbstractControl,
  FormBuilder,
  ReactiveFormsModule,
  ValidationErrors,
  ValidatorFn,
  Validators,
} from '@angular/forms';
import { finalize } from 'rxjs';
import { AdminSettingsDto, ApiClientError } from '../../../core/models/api.models';
import { AdminApiService } from '../../../core/services/admin-api.service';
import { formatIsoDate } from '../../../core/utils/admin-display';
import { formatDecimalString } from '../../../core/utils/money-display';
import { ConfirmDialog } from '../../../shared/components/confirm-dialog/confirm-dialog';

function positiveDecimalString(): ValidatorFn {
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
  selector: 'app-settings-page',
  imports: [ReactiveFormsModule, ConfirmDialog],
  templateUrl: './settings-page.html',
  styleUrl: './settings-page.scss',
})
export class SettingsPage implements OnInit {
  private readonly api = inject(AdminApiService);
  private readonly fb = inject(FormBuilder);

  readonly settings = signal<AdminSettingsDto | null>(null);
  readonly loading = signal(false);
  readonly saving = signal(false);
  readonly error = signal<string | null>(null);
  readonly success = signal<string | null>(null);
  readonly confirmOpen = signal(false);

  readonly formatIsoDate = formatIsoDate;
  readonly formatDecimalString = formatDecimalString;

  readonly form = this.fb.nonNullable.group({
    rub_to_cny_rate: ['', [Validators.required, positiveDecimalString()]],
  });

  ngOnInit(): void {
    this.load();
  }

  fieldError(): string | null {
    const control = this.form.controls.rub_to_cny_rate;
    if (!control.touched || !control.invalid) {
      return null;
    }
    if (control.hasError('required')) {
      return 'Exchange rate is required.';
    }
    if (control.hasError('numberFormat')) {
      return 'Enter a decimal number.';
    }
    if (control.hasError('positive')) {
      return 'Exchange rate must be greater than zero.';
    }
    return 'Invalid value.';
  }

  openConfirm(): void {
    this.success.set(null);
    this.error.set(null);
    this.form.markAllAsTouched();
    if (this.form.invalid || this.saving()) {
      return;
    }
    this.confirmOpen.set(true);
  }

  closeConfirm(): void {
    if (this.saving()) {
      return;
    }
    this.confirmOpen.set(false);
  }

  save(): void {
    if (this.form.invalid || this.saving()) {
      return;
    }

    const rate = this.form.controls.rub_to_cny_rate.getRawValue().trim();
    this.saving.set(true);
    this.error.set(null);

    this.api
      .updateExchangeRate(rate)
      .pipe(finalize(() => this.saving.set(false)))
      .subscribe({
        next: (settings) => {
          this.settings.set(settings);
          this.form.controls.rub_to_cny_rate.setValue(settings.rub_to_cny_rate);
          this.confirmOpen.set(false);
          this.success.set('Exchange rate updated.');
        },
        error: (err: unknown) => {
          this.confirmOpen.set(false);
          if (err instanceof ApiClientError) {
            this.error.set(err.flatMessages().join(' '));
            return;
          }
          this.error.set('Failed to save exchange rate.');
        },
      });
  }

  private load(): void {
    this.loading.set(true);
    this.error.set(null);

    this.api
      .getSettings()
      .pipe(finalize(() => this.loading.set(false)))
      .subscribe({
        next: (settings) => {
          this.settings.set(settings);
          this.form.controls.rub_to_cny_rate.setValue(settings.rub_to_cny_rate);
        },
        error: (err: unknown) => {
          if (err instanceof ApiClientError) {
            this.error.set(err.message);
            return;
          }
          this.error.set('Failed to load settings.');
        },
      });
  }
}
