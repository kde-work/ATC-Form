<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Проверяет единый JSON-формат ошибок публичного API.
 */
class ApiErrorFormatTest extends TestCase
{
    public function test_unknown_api_route_returns_not_found_payload(): void
    {
        $response = $this->getJson('/api/v1/does-not-exist');

        $response->assertNotFound();
        $response->assertJsonPath('code', 'not_found');
        $response->assertJsonPath('message', 'Resource not found.');
        $response->assertJsonStructure([
            'message',
            'code',
            'errors',
        ]);
    }
}
