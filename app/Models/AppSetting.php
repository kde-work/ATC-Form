<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AppSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ключ-значение настроек приложения (курс и прочие скаляры).
 *
 * @property int $id
 * @property string $key
 * @property string $value
 * @property int|null $updated_by_user_id
 */
class AppSetting extends Model
{
    /** @use HasFactory<AppSettingFactory> */
    use HasFactory;

    public const KEY_RUB_TO_CNY_RATE = 'rub_to_cny_rate';

    /**
     * Стартовое значение курса после seed (заглушка, не рыночный курс).
     */
    public const DEFAULT_RUB_TO_CNY_RATE = '0.085';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
        'updated_by_user_id',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
