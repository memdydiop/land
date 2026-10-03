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
        Schema::create('progress_reports', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('construction_site_id');
            $table->ulid('author_id');

            $table->date('report_date');

            $table->unsignedSmallInteger('progress_percentage');

            $table->text('summary');
            $table->text('issues')->nullable();
            $table->text('recommendations')->nullable();

            $table->timestampsTz();

            // A progress report belongs strictly to one construction site.
            $table->foreign('construction_site_id')
                ->references('id')
                ->on('construction_sites')
                ->cascadeOnDelete();

            // Preserve historical reports when an author account is removed.
            $table->foreign('author_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            // Query indexes.
            $table->index(['construction_site_id', 'report_date']);
            $table->index('author_id');
            $table->index('report_date');
        });

        // Progress percentage must remain between 0 and 100%.
        DB::statement("
            ALTER TABLE progress_reports
            ADD CONSTRAINT progress_reports_percentage_check
            CHECK (
                progress_percentage BETWEEN 0 AND 100
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('progress_reports');
    }
};