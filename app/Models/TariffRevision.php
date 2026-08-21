<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RevisionStatus;
use Database\Factories\TariffRevisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

/**
 * Версия набора тарифов. Калькулятор читает только status=active.
 *
 * @property int $id
 * @property int|null $tariff_import_id
 * @property int $version_number
 * @property RevisionStatus $status
 * @property int|null $activated_by_user_id
 * @property int|null $active_guard
 * @property array<string, mixed>|null $metadata_json
 * @property \Illuminate\Support\Carbon|null $activated_at
 * @property \Illuminate\Support\Carbon|null $archived_at
 */
class TariffRevision extends Model
{
    /** @use HasFactory<TariffRevisionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tariff_import_id',
        'version_number',
        'status',
        'activated_by_user_id',
        'activated_at',
        'archived_at',
        'metadata_json',
        'active_guard',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RevisionStatus::class,
            'version_number' => 'integer',
            'metadata_json' => 'array',
            'activated_at' => 'datetime',
            'archived_at' => 'datetime',
            'active_guard' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (TariffRevision $revision): void {
            // В MySQL active_guard вычисляется СУБД; в SQLite пишем вручную.
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                unset($revision->attributes['active_guard']);

                return;
            }

            $revision->active_guard = $revision->status === RevisionStatus::Active ? 1 : null;
        });
    }

    /**
     * @return BelongsTo<TariffImport, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(TariffImport::class, 'tariff_import_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by_user_id');
    }

    /**
     * @return HasMany<DeliveryChannel, $this>
     */
    public function channels(): HasMany
    {
        return $this->hasMany(DeliveryChannel::class);
    }
}
