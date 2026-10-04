<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_quotes', function (Blueprint $table): void {
            $table->decimal('subtotal', 20, 4)->change();
            $table->decimal('tax', 20, 4)->change();
            $table->decimal('total', 20, 4)->change();
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->decimal('subtotal', 20, 4)->change();
            $table->decimal('tax', 20, 4)->change();
            $table->decimal('total', 20, 4)->change();
        });

        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->decimal('quantity', 20, 6)->change();
            $table->decimal('unit_price', 20, 4)->change();
            $table->decimal('tax_rate', 12, 6)->change();
            $table->decimal('total', 20, 4)->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->decimal('quantity', 15, 3)->change();
            $table->decimal('unit_price', 15, 2)->change();
            $table->decimal('tax_rate', 7, 4)->change();
            $table->decimal('total', 15, 2)->change();
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->decimal('subtotal', 15, 2)->change();
            $table->decimal('tax', 15, 2)->change();
            $table->decimal('total', 15, 2)->change();
        });

        Schema::table('supplier_quotes', function (Blueprint $table): void {
            $table->decimal('subtotal', 15, 2)->change();
            $table->decimal('tax', 15, 2)->change();
            $table->decimal('total', 15, 2)->change();
        });
    }
};
