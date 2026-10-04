<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Precision already handled by 100001.
        Schema::table('contracts', function (Blueprint $table): void {
            $table->index(['party_id', 'status']);
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropIndex(['party_id', 'status']);
            $table->dropIndex(['project_id', 'status']);
        });
    }
};
