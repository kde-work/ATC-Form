import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { catchError, finalize, of, switchMap } from 'rxjs';
import { ApiClientError } from '../../../core/models/api.models';
import { AdminApiService } from '../../../core/services/admin-api.service';
import { AdminAuthService } from '../../../core/services/admin-auth.service';
import { adminRoute } from '../../../core/constants/admin-path';
import { SiteHeader } from '../../../shared/components/site-header/site-header';

@Component({
  selector: 'app-admin-login-page',
  imports: [ReactiveFormsModule, SiteHeader],
  templateUrl: './admin-login-page.html',
  styleUrl: './admin-login-page.scss',
})
export class AdminLoginPage {
  private readonly auth = inject(AdminAuthService);
  private readonly api = inject(AdminApiService);
  private readonly fb = inject(FormBuilder);
  private readonly router = inject(Router);

  readonly submitting = signal(false);
  readonly formError = signal<string | null>(null);
  readonly fieldErrors = signal<Record<string, string[]>>({});

  readonly form = this.fb.nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required]],
  });

  fieldError(name: 'email' | 'password'): string | null {
    const fromApi = this.fieldErrors()[name];
    if (fromApi && fromApi.length > 0) {
      return fromApi[0] ?? null;
    }

    const control = this.form.controls[name];
    if (!control.touched || !control.invalid) {
      return null;
    }

    if (control.hasError('required')) {
      return 'This field is required.';
    }
    if (control.hasError('email')) {
      return 'Enter a valid email address.';
    }

    return 'Invalid value.';
  }

  onSubmit(): void {
    this.formError.set(null);
    this.fieldErrors.set({});
    this.form.markAllAsTouched();

    if (this.form.invalid || this.submitting()) {
      return;
    }

    const { email, password } = this.form.getRawValue();
    this.submitting.set(true);

    this.auth
      .login(email.trim(), password)
      .pipe(
        switchMap(() => this.api.prefetchWarmup().pipe(catchError(() => of(undefined)))),
        finalize(() => this.submitting.set(false)),
      )
      .subscribe({
        next: () => void this.router.navigateByUrl(adminRoute('imports')),
        error: (error: unknown) => {
          if (error instanceof ApiClientError) {
            this.fieldErrors.set(error.fieldErrors);
            this.formError.set(error.message);
            return;
          }

          this.formError.set('Unable to sign in. Please try again.');
        },
      });
  }
}
