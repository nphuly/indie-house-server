<?php

namespace Tests\Unit\Backup;

use App\Domain\Backup\Services\BackupService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackupGeneratedColumnTest extends TestCase
{
    public function test_insertable_columns_excludes_generated_columns_in_order(): void
    {
        DB::statement(
            'CREATE TABLE demo_generated (
                id INTEGER PRIMARY KEY,
                total INTEGER,
                paid INTEGER,
                balance INTEGER GENERATED ALWAYS AS (total - paid) STORED
            )'
        );

        $columns = app(BackupService::class)->insertableColumns('demo_generated');

        $this->assertSame(['id', 'total', 'paid'], $columns);
        $this->assertNotContains('balance', $columns);
    }
}
