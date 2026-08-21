import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { catchError, from, switchMap, throwError } from 'rxjs';
import { ApiClientError, ApiErrorBody } from '../models/api.models';

function isApiErrorBody(value: unknown): value is ApiErrorBody {
  if (typeof value !== 'object' || value === null) {
    return false;
  }

  const record = value as Record<string, unknown>;
  return typeof record['message'] === 'string';
}

function apiClientErrorFromBody(status: number, body: ApiErrorBody): ApiClientError {
  return new ApiClientError(status, {
    message: body.message,
    code: typeof body.code === 'string' ? body.code : 'http_error',
    errors:
      typeof body.errors === 'object' && body.errors !== null
        ? (body.errors as Record<string, string[]>)
        : {},
  });
}

function toApiClientError(error: HttpErrorResponse): ApiClientError {
  if (isApiErrorBody(error.error)) {
    return apiClientErrorFromBody(error.status, error.error);
  }

  return new ApiClientError(error.status || 0, {
    message: error.message || 'Network or server error.',
    code: 'network_error',
    errors: {},
  });
}

/** Нормализует HTTP-ошибки API в ApiClientError (в т.ч. JSON в Blob при responseType: blob). */
export const apiErrorInterceptor: HttpInterceptorFn = (req, next) => {
  return next(req).pipe(
    catchError((error: unknown) => {
      if (!(error instanceof HttpErrorResponse)) {
        return throwError(() => error);
      }

      if (error.error instanceof Blob) {
        return from(error.error.text()).pipe(
          switchMap((text) => {
            try {
              const parsed: unknown = JSON.parse(text);
              if (isApiErrorBody(parsed)) {
                return throwError(() => apiClientErrorFromBody(error.status, parsed));
              }
            } catch {
              // тело не JSON: оставляем общий fallback ниже
            }

            return throwError(() => toApiClientError(error));
          }),
        );
      }

      return throwError(() => toApiClientError(error));
    }),
  );
};
