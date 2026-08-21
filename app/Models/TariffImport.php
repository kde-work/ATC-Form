<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportStatus;
use Database\Factories\TariffImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Загруженный XLSX и результат его обработки.
 *
 * @property int $id
 * @property int $uploaded_by_user_id
 * @property string $original_filename
 * @property string $stored_path
 * @property string $file_hash
 * @property int $file_size_bytes
 * @property ImportStatus $status
 * @property array<string, mixed>|null $summary_json
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 */
class TariffImport extends Model
{
    /** @use HasFactory<TariffImportFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uploaded_by_user_id',
        'original_filename',
        'stored_path',
        'file_hash',
        'file_size_bytes',
        'status',
        'summary_json',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'summary_json' => 'array',
            'file_size_bytes' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /**
     * @return HasMany<TariffImportError, $this>
     */
    public function errors(): HasMany
    {
        return $this->hasMany(TariffImportError::class);
    }

    /**
     * @return HasOne<TariffRevision, $this>
     */
    public function revision(): HasOne
    {
        return $this->hasOne(TariffRevision::class);
    }
}
