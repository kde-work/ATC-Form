<?php

namespace Tests;

use App\Services\ExchangeRate\ExchangeRateSyncService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // По умолчанию не дергаем ЦБ на каждом API-запросе в тестах.
        Cache::put(
            ExchangeRateSyncService::CACHE_KEY_NEXT_DUE_AT,
            time() + 86_400,
            86_400,
        );
    }
}
