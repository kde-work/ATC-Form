<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\Platform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Фильтр GET /delivery-channels?platform=
 */
final class DeliveryChannelIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<\Illuminate\Validation\Rules\Enum|string>>
     */
    public function rules(): array
    {
        return [
            'platform' => ['required', 'string', Rule::enum(Platform::class)],
        ];
    }

    public function platform(): Platform
    {
        return Platform::from((string) $this->validated('platform'));
    }
}
