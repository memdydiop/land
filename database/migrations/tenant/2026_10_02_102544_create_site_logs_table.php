<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('site_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('construction_site_id');
            $table->ulid('user_id');

            $table->date('log_date');

            $table->string('title');
            $table->text('content');

            $table->string('weather')->nullable();

            $table->unsignedInteger('workers_count')->nullable();

            $table->text('notes')->nullable();

            $table->timestampsTz();

            // A site log belongs strictly to one construction site.
            $table->foreign('construction_site_id')
                ->references('id')
                ->on('construction_sites')
                ->cascadeOnDelete();

            // Preserve historical logs when a user account is removed.
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            // Query indexes.
            $table->index(['construction_site_id', 'log_date']);
            $table->index('user_id');
            $table->index('log_date');
        });

        // Worker count cannot be negative.
        DB::statement("
            ALTER TABLE site_logs
            ADD CONSTRAINT site_logs_workers_count_check
            CHECK (
                workers_count IS NULL
                OR workers_count >= 0
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_logs');
    }
};