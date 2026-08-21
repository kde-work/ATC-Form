<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\ExchangeRate\ExchangeRateSyncService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * WP-cron style: при API-запросе планирует фоновое обновление курса, если пора.
 */
final class ScheduleExchangeRateSync
{
    public function __construct(
        private readonly ExchangeRateSyncService $exchangeRateSync,
    ) {
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->exchangeRateSync->scheduleIfDue();

        return $response;
    }
}
