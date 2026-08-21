<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Загрузка XLSX: POST /admin/imports.
 */
final class StoreImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|int>>
     */
    public function rules(): array
    {
        $maxKilobytes = (int) ceil(((int) config('atc.tariff_import_max_bytes', 5_242_880)) / 1024);

        return [
            'file' => [
                'required',
                'file',
                'extensions:xlsx',
                'max:' . $maxKilobytes,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'An XLSX tariff workbook is required.',
            'file.extensions' => 'Only .xlsx files are allowed.',
            'file.max' => 'The file exceeds the maximum allowed size.',
        ];
    }
}
