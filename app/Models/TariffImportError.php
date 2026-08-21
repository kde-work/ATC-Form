<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ошибка валидации или разбора импорта тарифов.
 *
 * @property int $id
 * @property int $tariff_import_id
 * @property string|null $sheet_name
 * @property int|null $row_number
 * @property string|null $field
 * @property string|null $error_code
 * @property string $message
 * @property array<string, mixed>|null $context_json
 */
class TariffImportError extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'tariff_import_id',
        'sheet_name',
        'row_number',
        'field',
        'error_code',
        'message',
        'context_json',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'context_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<TariffImport, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(TariffImport::class, 'tariff_import_id');
    }
}
