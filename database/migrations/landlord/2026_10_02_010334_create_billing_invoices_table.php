<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_invoices', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->ulid('tenant_id');
            $table->string('number')->unique();

            $table->string('status');

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);

            $table->char('currency', 3)->default('XOF');

            $table->timestampTz('issued_at');
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('paid_at')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->restrictOnDelete();

            $table->index('tenant_id');
            $table->index('status');
            $table->index('issued_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_invoices');
    }
};