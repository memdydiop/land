<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_require_pending_initial_refund()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            BEGIN
                IF NEW.type = 'refund'
                   AND NEW.status <> 'pending'
                THEN
                    RAISE EXCEPTION
                        'A new refund must start in pending status.'
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $function$
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_require_pending_initial_refund_trigger
            BEFORE INSERT ON payments
            FOR EACH ROW
            EXECUTE FUNCTION terra_require_pending_initial_refund()
            SQL);
    }

    public function down(): void
    {
        DB::statement(
            'DROP TRIGGER IF EXISTS terra_require_pending_initial_refund_trigger ON payments'
        );

        DB::statement(
            'DROP FUNCTION IF EXISTS terra_require_pending_initial_refund()'
        );
    }
};
