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
        Schema::create('subscription_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->ulid('subscription_id');

            $table->string('code');

            $table->unsignedInteger('quantity')->default(1);

            $table->decimal('unit_price', 15, 2)->default(0);

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->foreign('subscription_id')
                ->references('id')
                ->on('subscriptions')
                ->cascadeOnDelete();

            $table->unique(['subscription_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_items');
    }
};
