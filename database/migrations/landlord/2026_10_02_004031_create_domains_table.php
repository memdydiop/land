<?php

declare(strict_types=1);

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
        Schema::create('domains', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->ulid('tenant_id');

            $table->string('domain')->unique();

            $table->boolean('is_primary')->default(false);

            $table->timestampTz('verified_at')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->index('tenant_id');
        });

        DB::statement(
            'CREATE UNIQUE INDEX domains_one_primary_per_tenant_unique
             ON domains (tenant_id)
             WHERE is_primary = TRUE'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
