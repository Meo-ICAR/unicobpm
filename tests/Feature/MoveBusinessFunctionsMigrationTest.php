<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MoveBusinessFunctionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_nothing_is_dropped_when_the_shared_connection_points_to_the_same_database(): void
    {
        config([
            'database.connections.core_alias' => config('database.connections.'.config('database.default')),
            'unico-core.connection' => 'core_alias',
        ]);
        DB::table('business_functions')->insert(['id' => 99, 'code' => 'TEST', 'name' => 'Funzione di prova', 'macro_area' => 'Governance', 'type' => 'Strategica', 'outsourcable_status' => 'no']);

        $migration = require database_path('migrations/2026_10_16_210000_move_business_functions_to_core.php');
        $migration->up();

        $this->assertTrue(Schema::hasTable('business_functions'));
        $this->assertTrue(Schema::hasTable('business_function_members'));
        $this->assertTrue(DB::table('business_functions')->where('id', 99)->exists());
    }
}
