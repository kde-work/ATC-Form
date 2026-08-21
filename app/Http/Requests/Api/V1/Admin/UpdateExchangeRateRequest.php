<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Support\Decimal;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

/**
 * Вход PUT /admin/settings/exchange-rate.
 */
final class UpdateExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'rub_to_cny_rate' => ['required'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $raw = $this->input('rub_to_cny_rate');
            if (! is_string($raw) && ! is_int($raw) && ! is_float($raw)) {
                $validator->errors()->add('rub_to_cny_rate', 'The exchange rate must be a decimal number.');

                return;
            }

            try {
                $normalized = Decimal::normalize((string) $raw, 8);
            } catch (InvalidArgumentException) {
                $validator->errors()->add('rub_to_cny_rate', 'The exchange rate must be a decimal number.');

                return;
            }

            if (Decimal::compare($normalized, '0', 8) <= 0) {
                $validator->errors()->add('rub_to_cny_rate', 'The exchange rate must be greater than zero.');
            }
        });
    }

    /**
     * Нормализованная decimal-строка курса.
     *
     * @return numeric-string
     */
    public function rate(): string
    {
        /** @var numeric-string $rate */
        $rate = Decimal::normalize((string) $this->input('rub_to_cny_rate'), 8);

        return $rate;
    }
}
