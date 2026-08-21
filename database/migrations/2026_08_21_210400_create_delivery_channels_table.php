<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('delivery_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tariff_revision_id')->constrained('tariff_revisions')->cascadeOnDelete();
            $table->string('platform', 32);
            $table->string('code');
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->string('currency', 8);
            $table->string('chargeable_weight_type', 64)->nullable();
            $table->decimal('fixed_fee', 14, 6);
            $table->decimal('per_gram_fee', 14, 8)->nullable();
            $table->decimal('per_kg_fee', 14, 6)->nullable();
            $table->unsignedInteger('billing_increment_grams')->nullable();
            $table->decimal('volumetric_divisor', 14, 4)->nullable();
            $table->decimal('min_weight_grams', 14, 3)->nullable();
            $table->decimal('max_weight_grams', 14, 3)->nullable();
            $table->decimal('max_length_cm', 14, 3)->nullable();
            $table->decimal('max_sum_dimensions_cm', 14, 3)->nullable();
            $table->decimal('min_order_cost_rub', 14, 4)->nullable();
            $table->decimal('max_order_cost_rub', 14, 4)->nullable();
            $table->decimal('min_order_cost_cny', 14, 4)->nullable();
            $table->decimal('max_order_cost_cny', 14, 4)->nullable();
            $table->unsignedInteger('import_source_row')->nullable();
            $table->json('raw_data_json')->nullable();
            $table->timestamps();

            $table->unique(['tariff_revision_id', 'platform', 'code'], 'delivery_channels_revision_platform_code_unique');
            $table->index(['tariff_revision_id', 'platform', 'active'], 'delivery_channels_revision_platform_active_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_channels');
    }
};
