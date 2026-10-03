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
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->ulid('tenant_id');
            $table->ulid('plan_id');

            /*
             * Stored as VARCHAR.
             * Domain value is represented by App\Enums\SubscriptionStatus.
             */
            $table->string('status');

            $table->timestampTz('starts_at');

            $table->timestampTz('trial_ends_at')->nullable();

            $table->timestampTz('current_period_start');
            $table->timestampTz('current_period_end');

            $table->timestampTz('cancelled_at')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->restrictOnDelete();

            $table->foreign('plan_id')
                ->references('id')
                ->on('plans')
                ->restrictOnDelete();

            $table->index('tenant_id');
            $table->index('plan_id');
            $table->index('status');
        });

        /*
         * A tenant may have historical subscriptions, but only one
         * trialing/active subscription at a time.
         */
        DB::statement(
            "CREATE UNIQUE INDEX subscriptions_one_current_per_tenant_unique
             ON subscriptions (tenant_id)
             WHERE status IN ('trialing', 'active')"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
