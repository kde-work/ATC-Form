<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\Platform;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Элемент списка платформ.
 *
 * @property Platform $resource
 */
final class PlatformResource extends JsonResource
{
    /**
     * @return array{code: string, name: string}
     */
    public function toArray(Request $request): array
    {
        /** @var Platform $platform */
        $platform = $this->resource;

        return [
            'code' => $platform->value,
            'name' => $platform->label(),
        ];
    }
}
