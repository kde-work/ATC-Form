<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Dto\Calculation\CalculationInput;
use App\Exceptions\InvalidCalculationInputException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Вход POST /calculations. Доменная нормализация в CalculationInput.
 */
final class CalculateRequest extends FormRequest
{
    private ?CalculationInput $calculationInput = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Базовые ключи; детальная нормализация в after-валидаторе.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'platform' => ['required'],
            'delivery_channel_code' => ['required'],
            'physical_weight_grams' => ['required'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            try {
                /** @var array<string, mixed> $payload */
                $payload = $this->all();
                $this->calculationInput = CalculationInput::fromArray($payload);
            } catch (InvalidCalculationInputException $exception) {
                foreach ($exception->errors as $field => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($field, $message);
                    }
                }
            }
        });
    }

    public function calculationInput(): CalculationInput
    {
        if ($this->calculationInput === null) {
            /** @var array<string, mixed> $payload */
            $payload = $this->all();
            $this->calculationInput = CalculationInput::fromArray($payload);
        }

        return $this->calculationInput;
    }
}
