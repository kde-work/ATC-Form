<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\ImportStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Фильтры GET /admin/imports.
 */
final class ImportIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|\Illuminate\Validation\Rules\Enum|int>>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', Rule::enum(ImportStatus::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function status(): ?ImportStatus
    {
        $value = $this->query('status');
        if (! is_string($value) || $value === '') {
            return null;
        }

        return ImportStatus::from($value);
    }

    public function perPage(): int
    {
        $value = $this->query('per_page', 15);

        return max(1, min(100, (int) $value));
    }
}
