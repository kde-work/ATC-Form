<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\TariffImportError;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ошибка валидации/парсинга импорта.
 *
 * @mixin TariffImportError
 */
final class TariffImportErrorResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     sheet_name: string|null,
     *     row_number: int|null,
     *     field: string|null,
     *     error_code: string|null,
     *     message: string,
     *     context: array<string, mixed>|null
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var TariffImportError $error */
        $error = $this->resource;

        return [
            'id' => $error->id,
            'sheet_name' => $error->sheet_name,
            'row_number' => $error->row_number,
            'field' => $error->field,
            'error_code' => $error->error_code,
            'message' => $error->message,
            'context' => $error->context_json,
        ];
    }
}
