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
        Schema::create('tariff_import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tariff_import_id')->constrained('tariff_imports')->cascadeOnDelete();
            $table->string('sheet_name')->nullable();
            $table->unsignedInteger('row_number')->nullable();
            $table->string('field')->nullable();
            $table->string('error_code')->nullable();
            $table->text('message');
            $table->json('context_json')->nullable();
            $table->timestamps();

            $table->index('tariff_import_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tariff_import_errors');
    }
};
