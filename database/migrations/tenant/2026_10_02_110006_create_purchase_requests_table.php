<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reference');
            $table->ulid('requested_by');
            $table->ulid('project_id')->nullable();
            $table->ulid('construction_site_id')->nullable();
            $table->string('status');
            $table->text('description')->nullable();
            $table->timestampTz('requested_at');
            $table->timestampTz('approved_at')->nullable();
            $table->timestampsTz();

            $table->unique('reference');

            $table->foreign('requested_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->foreign('construction_site_id')->references('id')->on('construction_sites')->nullOnDelete();

            $table->index('requested_by');
            $table->index('project_id');
            $table->index('construction_site_id');
            $table->index('status');
            $table->index('requested_at');
        });

        DB::statement("ALTER TABLE purchase_requests ADD CONSTRAINT purchase_requests_status_check CHECK (status IN ('draft','submitted','approved','rejected','ordered','completed','cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
