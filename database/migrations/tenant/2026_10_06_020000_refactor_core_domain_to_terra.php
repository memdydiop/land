<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('land_operations') && ! Schema::hasTable('operations')) {
            Schema::rename('land_operations', 'operations');
        }

        if (Schema::hasTable('operations')) {
            DB::statement('ALTER TABLE operations DROP CONSTRAINT IF EXISTS land_operations_type_check');
            DB::statement('ALTER TABLE operations DROP CONSTRAINT IF EXISTS land_operations_status_check');
            DB::statement('ALTER TABLE operations DROP CONSTRAINT IF EXISTS land_operations_dates_check');

            Schema::table('operations', function (Blueprint $table): void {
                if (! Schema::hasColumn('operations', 'manager_id')) {
                    $table->ulid('manager_id')->nullable();
                }

                if (! Schema::hasColumn('operations', 'metadata')) {
                    $table->jsonb('metadata')->nullable();
                }

                if (! Schema::hasColumn('operations', 'deleted_at')) {
                    $table->softDeletesTz();
                }
            });

            $hasProjectId = Schema::hasColumn('operations', 'project_id');

            if ($hasProjectId) {
                DB::statement("
                    DO $$
                    BEGIN
                        IF EXISTS (
                            SELECT 1
                            FROM operations
                            WHERE project_id IS NOT NULL
                            GROUP BY project_id
                            HAVING COUNT(*) > 1
                        ) THEN
                            RAISE EXCEPTION
                                'Cannot migrate land_operations.project_id: a project is linked to multiple land operations.';
                        END IF;
                    END
                    $$;
                ");

                if (! Schema::hasColumn('projects', 'operation_id')) {
                    Schema::table('projects', function (Blueprint $table): void {
                        $table->ulid('operation_id')->nullable();
                    });
                }

                DB::statement("
                    UPDATE projects AS p
                    SET operation_id = o.id
                    FROM operations AS o
                    WHERE o.project_id = p.id
                      AND p.operation_id IS NULL
                ");

                DB::statement('ALTER TABLE operations DROP CONSTRAINT IF EXISTS land_operations_project_id_foreign');
                DB::statement('ALTER TABLE operations DROP CONSTRAINT IF EXISTS operations_project_id_foreign');
                DB::statement('DROP INDEX IF EXISTS land_operations_project_id_index');
                DB::statement('DROP INDEX IF EXISTS operations_project_id_index');

                Schema::table('operations', function (Blueprint $table): void {
                    $table->dropColumn('project_id');
                });
            }

            DB::statement("
                UPDATE operations
                SET type = CASE type
                    WHEN 'subdivision' THEN 'land_development'
                    WHEN 'lotissement' THEN 'land_development'
                    WHEN 'land_development' THEN 'land_development'
                    WHEN 'redevelopment' THEN 'mixed'
                    ELSE 'other'
                END
            ");

            DB::statement("
                UPDATE operations
                SET status = CASE status
                    WHEN 'draft' THEN 'draft'
                    WHEN 'study' THEN 'planned'
                    WHEN 'administrative' THEN 'planned'
                    WHEN 'approved' THEN 'active'
                    WHEN 'in_progress' THEN 'active'
                    WHEN 'completed' THEN 'completed'
                    WHEN 'cancelled' THEN 'cancelled'
                    WHEN 'archived' THEN 'archived'
                    ELSE 'draft'
                END
            ");

            DB::statement("
                ALTER TABLE operations
                ADD CONSTRAINT operations_type_check
                CHECK (
                    type IN (
                        'land_development',
                        'real_estate_development',
                        'construction',
                        'infrastructure',
                        'mixed',
                        'other'
                    )
                )
            ");

            DB::statement("
                ALTER TABLE operations
                ADD CONSTRAINT operations_status_check
                CHECK (
                    status IN (
                        'draft',
                        'planned',
                        'active',
                        'on_hold',
                        'completed',
                        'cancelled',
                        'archived'
                    )
                )
            ");

            DB::statement("
                ALTER TABLE operations
                ADD CONSTRAINT operations_dates_check
                CHECK (
                    start_date IS NULL
                    OR end_date IS NULL
                    OR end_date >= start_date
                )
            ");

            if (! Schema::hasColumn('operations', 'manager_id')) {
                throw new RuntimeException('Failed to create operations.manager_id.');
            }

            Schema::table('operations', function (Blueprint $table): void {
                $table->foreign('manager_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->index('manager_id');
            });
        }

        if (Schema::hasTable('land_operation_lands') && ! Schema::hasTable('operation_lands')) {
            Schema::rename('land_operation_lands', 'operation_lands');
        }

        if (Schema::hasTable('operation_lands') && Schema::hasColumn('operation_lands', 'land_operation_id')) {
            DB::statement(
                'ALTER TABLE operation_lands RENAME COLUMN land_operation_id TO operation_id'
            );
        }

        if (Schema::hasTable('subdivisions') && Schema::hasColumn('subdivisions', 'land_operation_id')) {
            DB::statement(
                'ALTER TABLE subdivisions RENAME COLUMN land_operation_id TO operation_id'
            );
        }

        $operationLinkedTables = [
            'projects',
            'properties',
            'contracts',
            'quotes',
            'purchase_requests',
            'purchase_orders',
            'budgets',
            'expenses',
            'invoices',
        ];

        foreach ($operationLinkedTables as $tableName) {
            if (! Schema::hasColumn($tableName, 'operation_id')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->ulid('operation_id')->nullable();
                });
            }
        }

        foreach ($operationLinkedTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreign('operation_id')
                    ->references('id')
                    ->on('operations')
                    ->nullOnDelete();

                $table->index('operation_id');
            });
        }

        Schema::create('subdivision_lands', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('subdivision_id');
            $table->ulid('land_id');
            $table->timestampsTz();

            $table->unique(['subdivision_id', 'land_id']);

            $table->foreign('subdivision_id')
                ->references('id')
                ->on('subdivisions')
                ->cascadeOnDelete();

            $table->foreign('land_id')
                ->references('id')
                ->on('lands')
                ->cascadeOnDelete();

            $table->index('land_id');
        });

        Schema::create('lots', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('operation_id');
            $table->string('reference');
            $table->string('name')->nullable();
            $table->ulid('parcel_id')->nullable();
            $table->ulid('unit_id')->nullable();
            $table->string('status');
            $table->decimal('price', 20, 4);
            $table->char('currency', 3);
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->unique('reference');
            $table->unique(['operation_id', 'id']);

            $table->foreign('operation_id')
                ->references('id')
                ->on('operations')
                ->restrictOnDelete();

            $table->foreign('parcel_id')
                ->references('id')
                ->on('parcels')
                ->restrictOnDelete();

            $table->foreign('unit_id')
                ->references('id')
                ->on('units')
                ->restrictOnDelete();

            $table->index(['operation_id', 'status']);
            $table->index('parcel_id');
            $table->index('unit_id');
        });

        DB::statement('
            ALTER TABLE lots
            ADD CONSTRAINT lots_source_check
            CHECK (num_nonnulls(parcel_id, unit_id) = 1)
        ');

        DB::statement("
            ALTER TABLE lots
            ADD CONSTRAINT lots_status_check
            CHECK (
                status IN (
                    'draft',
                    'available',
                    'blocked',
                    'withdrawn',
                    'archived'
                )
            )
        ");

        DB::statement('
            ALTER TABLE lots
            ADD CONSTRAINT lots_price_check
            CHECK (price >= 0)
        ');

        DB::statement('
            CREATE UNIQUE INDEX lots_operation_parcel_unique
            ON lots (operation_id, parcel_id)
            WHERE parcel_id IS NOT NULL
        ');

        DB::statement('
            CREATE UNIQUE INDEX lots_operation_unit_unique
            ON lots (operation_id, unit_id)
            WHERE unit_id IS NOT NULL
        ');

        Schema::create('reservations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('lot_id');
            $table->ulid('party_id');
            $table->string('reference');
            $table->string('status');
            $table->timestampTz('reserved_at');
            $table->timestampTz('expires_at')->nullable();
            $table->decimal('reserved_amount', 20, 4);
            $table->decimal('deposit_amount', 20, 4)->default(0);
            $table->char('currency', 3);
            $table->ulid('created_by')->nullable();
            $table->timestampTz('converted_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique('reference');

            $table->foreign('lot_id')
                ->references('id')
                ->on('lots')
                ->restrictOnDelete();

            $table->foreign('party_id')
                ->references('id')
                ->on('parties')
                ->restrictOnDelete();

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['lot_id', 'status']);
            $table->index('party_id');
            $table->index('expires_at');
        });

        DB::statement("
            ALTER TABLE reservations
            ADD CONSTRAINT reservations_status_check
            CHECK (
                status IN (
                    'pending',
                    'confirmed',
                    'expired',
                    'cancelled',
                    'converted'
                )
            )
        ");

        DB::statement("
            ALTER TABLE reservations
            ADD CONSTRAINT reservations_amounts_check
            CHECK (
                reserved_amount >= 0
                AND deposit_amount >= 0
                AND deposit_amount <= reserved_amount
            )
        ");

        DB::statement("
            ALTER TABLE reservations
            ADD CONSTRAINT reservations_dates_check
            CHECK (
                expires_at IS NULL
                OR expires_at > reserved_at
            )
        ");

        DB::statement("
            CREATE UNIQUE INDEX reservations_active_lot_unique
            ON reservations (lot_id)
            WHERE status IN ('pending', 'confirmed')
        ");

        Schema::create('sales', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('operation_id');
            $table->ulid('lot_id');
            $table->ulid('reservation_id')->nullable();
            $table->ulid('buyer_party_id');
            $table->string('reference');
            $table->string('status');
            $table->date('sale_date');
            $table->decimal('base_amount', 20, 4);
            $table->decimal('discount_amount', 20, 4)->default(0);
            $table->decimal('net_amount', 20, 4);
            $table->char('currency', 3);
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->unique('reference');

            $table->foreign('operation_id')
                ->references('id')
                ->on('operations')
                ->restrictOnDelete();

            $table->foreign(['operation_id', 'lot_id'])
                ->references(['operation_id', 'id'])
                ->on('lots')
                ->restrictOnDelete();

            $table->foreign('reservation_id')
                ->references('id')
                ->on('reservations')
                ->restrictOnDelete();

            $table->foreign('buyer_party_id')
                ->references('id')
                ->on('parties')
                ->restrictOnDelete();

            $table->index(['operation_id', 'status']);
            $table->index('lot_id');
            $table->index('reservation_id');
            $table->index('buyer_party_id');
            $table->index('sale_date');
        });

        DB::statement("
            ALTER TABLE sales
            ADD CONSTRAINT sales_status_check
            CHECK (
                status IN (
                    'draft',
                    'confirmed',
                    'completed',
                    'cancelled'
                )
            )
        ");

        DB::statement("
            ALTER TABLE sales
            ADD CONSTRAINT sales_amounts_check
            CHECK (
                base_amount >= 0
                AND discount_amount >= 0
                AND discount_amount <= base_amount
                AND net_amount >= 0
                AND net_amount = base_amount - discount_amount
            )
        ");

        DB::statement("
            CREATE UNIQUE INDEX sales_active_lot_unique
            ON sales (lot_id)
            WHERE status IN ('confirmed', 'completed')
        ");

        DB::statement("
            CREATE UNIQUE INDEX sales_reservation_unique
            ON sales (reservation_id)
            WHERE reservation_id IS NOT NULL
        ");

        Schema::table('contracts', function (Blueprint $table): void {
            $table->ulid('sale_id')->nullable();
            $table->foreign('sale_id')
                ->references('id')
                ->on('sales')
                ->nullOnDelete();
            $table->index('sale_id');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->ulid('sale_id')->nullable();
            $table->foreign('sale_id')
                ->references('id')
                ->on('sales')
                ->nullOnDelete();
            $table->index('sale_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign(['sale_id']);
            $table->dropIndex(['sale_id']);
            $table->dropColumn('sale_id');
        });

        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropForeign(['sale_id']);
            $table->dropIndex(['sale_id']);
            $table->dropColumn('sale_id');
        });

        DB::statement('DROP INDEX IF EXISTS sales_reservation_unique');
        DB::statement('DROP INDEX IF EXISTS sales_active_lot_unique');
        Schema::dropIfExists('sales');

        DB::statement('DROP INDEX IF EXISTS reservations_active_lot_unique');
        Schema::dropIfExists('reservations');

        DB::statement('DROP INDEX IF EXISTS lots_operation_unit_unique');
        DB::statement('DROP INDEX IF EXISTS lots_operation_parcel_unique');
        Schema::dropIfExists('lots');

        Schema::dropIfExists('subdivision_lands');

        const operationLinkedTables = [
            'invoices',
            'expenses',
            'budgets',
            'purchase_orders',
            'purchase_requests',
            'quotes',
            'contracts',
            'properties',
            'projects',
        ];

        for (const tableName of operationLinkedTables.reverse()) {
            Schema::table(tableName, function (Blueprint $table): void {
                $table->dropForeign(['operation_id']);
                $table->dropIndex(['operation_id']);
                $table->dropColumn('operation_id');
            });
        }

        if (Schema::hasTable('subdivisions') && Schema::hasColumn('subdivisions', 'operation_id')) {
            DB::statement(
                'ALTER TABLE subdivisions RENAME COLUMN operation_id TO land_operation_id'
            );
        }

        if (Schema::hasTable('operation_lands') && Schema::hasColumn('operation_lands', 'operation_id')) {
            DB::statement(
                'ALTER TABLE operation_lands RENAME COLUMN operation_id TO land_operation_id'
            );
        }

        if (Schema::hasTable('operation_lands') && ! Schema::hasTable('land_operation_lands')) {
            Schema::rename('operation_lands', 'land_operation_lands');
        }

        if (Schema::hasTable('operations')) {
            DB::statement('ALTER TABLE operations DROP CONSTRAINT IF EXISTS operations_type_check');
            DB::statement('ALTER TABLE operations DROP CONSTRAINT IF EXISTS operations_status_check');
            DB::statement('ALTER TABLE operations DROP CONSTRAINT IF EXISTS operations_dates_check');

            Schema::table('operations', function (Blueprint $table): void {
                $table->ulid('project_id')->nullable();
                $table->foreign('project_id')
                    ->references('id')
                    ->on('projects')
                    ->nullOnDelete();
                $table->index('project_id');

                $table->dropForeign(['manager_id']);
                $table->dropIndex(['manager_id']);
                $table->dropColumn([
                    'manager_id',
                    'metadata',
                    'deleted_at',
                ]);
            });

            if (! Schema::hasTable('land_operations')) {
                Schema::rename('operations', 'land_operations');
            }
        }

        if (Schema::hasColumn('projects', 'operation_id')) {
            Schema::table('projects', function (Blueprint $table): void {
                $table->dropColumn('operation_id');
            });
        }
    }
};
