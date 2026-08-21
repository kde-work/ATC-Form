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
        Schema::create('tariff_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('file_hash', 64);
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('status', 32);
            $table->json('summary_json')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tariff_imports');
    }
};
