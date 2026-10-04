<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase;

trait RefreshLandlordDatabase
{
    use RefreshDatabase {
        migrateFreshUsing as protected baseMigrateFreshUsing;
    }

    protected function migrateFreshUsing()
    {
        return array_merge(
            $this->baseMigrateFreshUsing(),
            [
                '--path' => base_path('database/migrations/landlord'),
                '--realpath' => true,
            ],
        );
    }
}
