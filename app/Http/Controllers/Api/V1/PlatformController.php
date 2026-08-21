<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Platform;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PlatformResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Публичный список платформ калькулятора.
 */
final class PlatformController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PlatformResource::collection(Platform::cases());
    }
}
