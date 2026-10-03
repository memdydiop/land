<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->string('name');
            $table->string('slug')->unique();

            /*
             * Stored as VARCHAR.
             * Domain state is represented by App\Enums\TenantStatus.
             */
            $table->string('status');

            /*
             * PostgreSQL schema used by Stancl Tenancy.
             * This is the business source of truth for the tenant schema name.
             */
            $table->string('schema_name')->unique();

            /*
             * Reserved for a future database-per-tenant strategy.
             * Nullable because V1 uses PostgreSQL schema-per-tenant.
             */
            $table->string('database_identifier')->nullable();

            $table->string('timezone')->default('UTC');
            $table->string('locale')->default('en');
            $table->char('currency', 3)->default('XOF');

            $table->timestampTz('trial_ends_at')->nullable();
            $table->timestampTz('suspended_at')->nullable();

            /*
             * Stancl's extensible data column.
             */
            $table->jsonb('data')->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
