<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_payments', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->ulid('invoice_id');

            $table->decimal('amount', 15, 2);
            $table->char('currency', 3)->default('XOF');

            $table->string('method');
            $table->string('reference')->nullable();
            $table->string('status');

            $table->timestampTz('paid_at')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->foreign('invoice_id')
                ->references('id')
                ->on('billing_invoices')
                ->restrictOnDelete();

            $table->index('invoice_id');
            $table->index('status');
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_payments');
    }
};