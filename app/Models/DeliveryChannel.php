<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Platform;
use App\Enums\WeightCalculationType;
use Database\Factories\DeliveryChannelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Снимок тарифа канала внутри ревизии.
 *
 * @property int $id
 * @property int $tariff_revision_id
 * @property Platform $platform
 * @property string $code
 * @property string $name
 * @property bool $active
 * @property string $currency
 * @property WeightCalculationType|null $chargeable_weight_type
 * @property string $fixed_fee
 * @property string|null $per_gram_fee
 * @property string|null $per_kg_fee
 * @property int|null $billing_increment_grams
 * @property string|null $volumetric_divisor
 * @property string|null $min_weight_grams
 * @property string|null $max_weight_grams
 * @property string|null $max_length_cm
 * @property string|null $max_sum_dimensions_cm
 * @property string|null $min_order_cost_rub
 * @property string|null $max_order_cost_rub
 * @property string|null $min_order_cost_cny
 * @property string|null $max_order_cost_cny
 * @property int|null $import_source_row
 * @property array<string, mixed>|null $raw_data_json
 */
class DeliveryChannel extends Model
{
    /** @use HasFactory<DeliveryChannelFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tariff_revision_id',
        'platform',
        'code',
        'name',
        'active',
        'currency',
        'chargeable_weight_type',
        'fixed_fee',
        'per_gram_fee',
        'per_kg_fee',
        'billing_increment_grams',
        'volumetric_divisor',
        'min_weight_grams',
        'max_weight_grams',
        'max_length_cm',
        'max_sum_dimensions_cm',
        'min_order_cost_rub',
        'max_order_cost_rub',
        'min_order_cost_cny',
        'max_order_cost_cny',
        'import_source_row',
        'raw_data_json',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'chargeable_weight_type' => WeightCalculationType::class,
            'active' => 'boolean',
            'billing_increment_grams' => 'integer',
            'import_source_row' => 'integer',
            'raw_data_json' => 'array',
            'fixed_fee' => 'decimal:6',
            'per_gram_fee' => 'decimal:8',
            'per_kg_fee' => 'decimal:6',
            'volumetric_divisor' => 'decimal:4',
            'min_weight_grams' => 'decimal:3',
            'max_weight_grams' => 'decimal:3',
            'max_length_cm' => 'decimal:3',
            'max_sum_dimensions_cm' => 'decimal:3',
            'min_order_cost_rub' => 'decimal:4',
            'max_order_cost_rub' => 'decimal:4',
            'min_order_cost_cny' => 'decimal:4',
            'max_order_cost_cny' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<TariffRevision, $this>
     */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(TariffRevision::class, 'tariff_revision_id');
    }
}
