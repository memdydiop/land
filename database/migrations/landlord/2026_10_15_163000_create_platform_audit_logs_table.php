<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_audit_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('event', 80);
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            $table->foreignUlid('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('actor_name');
            $table->string('actor_email');

            $table->foreignUlid('tenant_id')
                ->nullable()
                ->constrained('tenants')
                ->nullOnDelete();

            $table->string('tenant_name');
            $table->string('tenant_slug');
            $table->string('status', 24);
            $table->string('failure_code', 80)->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index(['event', 'status']);
            $table->index(['tenant_slug', 'started_at']);
            $table->index(['actor_email', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_logs');
    }
};
