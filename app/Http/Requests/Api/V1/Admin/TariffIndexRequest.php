<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\Platform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Фильтры GET /admin/tariffs.
 */
final class TariffIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|\Illuminate\Validation\Rules\Enum>>
     */
    public function rules(): array
    {
        return [
            'platform' => ['sometimes', 'nullable', Rule::enum(Platform::class)],
            'revision_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    public function platform(): ?Platform
    {
        $value = $this->query('platform');
        if (! is_string($value) || $value === '') {
            return null;
        }

        return Platform::from($value);
    }

    public function revisionId(): ?int
    {
        $value = $this->query('revision_id');
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
