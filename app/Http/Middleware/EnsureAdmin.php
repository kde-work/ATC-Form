<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Доступ к admin API: только аутентифицированный пользователь (роль admin в MVP).
 */
final class EnsureAdmin
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null) {
            throw new AuthenticationException('Unauthenticated.');
        }

        return $next($request);
    }
}
