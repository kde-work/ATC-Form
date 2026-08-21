<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tariff_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tariff_import_id')->nullable()->constrained('tariff_imports')->nullOnDelete();
            $table->unsignedInteger('version_number')->unique();
            $table->string('status', 32);
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        // Инвариант «не больше одной active»: несколько NULL допустимы, значение 1 только одно.
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                'ALTER TABLE tariff_revisions
                 ADD COLUMN active_guard TINYINT UNSIGNED
                 GENERATED ALWAYS AS (CASE WHEN `status` = \'active\' THEN 1 ELSE NULL END) STORED,
                 ADD UNIQUE KEY tariff_revisions_active_guard_unique (active_guard)'
            );
        } elseif ($driver === 'sqlite') {
            // SQLite в тестах: отдельная nullable-колонка с уникальностью; значение пишется из модели.
            Schema::table('tariff_revisions', function (Blueprint $table) {
                $table->unsignedTinyInteger('active_guard')->nullable()->unique();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tariff_revisions');
    }
};
