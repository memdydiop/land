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
        Schema::create('plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            $table->decimal('price', 20, 4)->default(0);
            $table->char('currency', 3)->default('XOF');

            /*
             * Stored as VARCHAR.
             * Domain value is represented by an application enum.
             */
            $table->string('billing_interval');

            $table->boolean('is_active')->default(true);

            /*
             * Limits and feature flags are intentionally flexible.
             */
            $table->jsonb('limits')->nullable();
            $table->jsonb('features')->nullable();

            $table->timestamps();

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
