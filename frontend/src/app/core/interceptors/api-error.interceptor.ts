import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { catchError, throwError } from 'rxjs';
import { ApiClientError, ApiErrorBody } from '../models/api.models';

function isApiErrorBody(value: unknown): value is ApiErrorBody {
  if (typeof value !== 'object' || value === null) {
    return false;
  }

  const record = value as Record<string, unknown>;
  return typeof record['message'] === 'string';
}

function toApiClientError(error: HttpErrorResponse): ApiClientError {
  if (isApiErrorBody(error.error)) {
    return new ApiClientError(error.status, {
      message: error.error.message,
      code: typeof error.error.code === 'string' ? error.error.code : 'http_error',
      errors:
        typeof error.error.errors === 'object' && error.error.errors !== null
          ? (error.error.errors as Record<string, string[]>)
          : {},
    });
  }

  return new ApiClientError(error.status || 0, {
    message: error.message || 'Network or server error.',
    code: 'network_error',
    errors: {},
  });
}

/** Нормализует HTTP-ошибки API в ApiClientError. */
export const apiErrorInterceptor: HttpInterceptorFn = (req, next) => {
  return next(req).pipe(
    catchError((error: unknown) => {
      if (error instanceof HttpErrorResponse) {
        return throwError(() => toApiClientError(error));
      }

      return throwError(() => error);
    }),
  );
};
