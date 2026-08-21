<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Единый JSON-ответ ошибок для /api/*.
 */
final class ApiErrorRenderer
{
    public function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if (!$this->shouldRender($request)) {
            return null;
        }

        if ($exception instanceof ValidationException) {
            return $this->response(
                'The given data was invalid.',
                'validation_error',
                422,
                $exception->errors(),
            );
        }

        if ($exception instanceof AuthenticationException) {
            return $this->response(
                'Unauthenticated.',
                'unauthenticated',
                401,
            );
        }

        if ($exception instanceof AuthorizationException) {
            return $this->response(
                'This action is unauthorized.',
                'forbidden',
                403,
            );
        }

        if ($exception instanceof ModelNotFoundException || $exception instanceof NotFoundHttpException) {
            return $this->response(
                'Resource not found.',
                'not_found',
                404,
            );
        }

        if ($exception instanceof TariffRevisionActivationException) {
            return $this->response(
                $exception->getMessage() !== '' ? $exception->getMessage() : 'Import cannot be activated.',
                'import_not_activatable',
                422,
                ['_' => [$exception->getMessage()]],
            );
        }

        if ($exception instanceof TariffImportFileException) {
            return $this->response(
                $exception->getMessage() !== '' ? $exception->getMessage() : 'Import file is invalid.',
                'import_file_error',
                422,
                ['file' => [$exception->getMessage()]],
            );
        }

        $status = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : 500;

        if ($status === 429) {
            return $this->response(
                'Too many requests.',
                'too_many_requests',
                429,
            );
        }

        $debug = (bool) config('app.debug');
        $message = $status >= 500 && !$debug
            ? 'Server error.'
            : ($exception->getMessage() !== '' ? $exception->getMessage() : 'Server error.');

        $code = $status >= 500 ? 'server_error' : 'http_error';

        return $this->response($message, $code, $status);
    }

    private function shouldRender(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    private function response(string $message, string $code, int $status, array $errors = []): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'code' => $code,
            'errors' => $errors,
        ], $status);
    }
}
