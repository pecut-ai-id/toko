<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Membuktikan suite pengujian benar-benar berjalan di koneksi MySQL
 * (database uji terpisah) dan bukan SQLite in-memory.
 */
class DatabaseConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_run_on_the_configured_mysql_connection(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame('tokoflow_test', DB::connection()->getDatabaseName());
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('migrations'));
    }

    public function test_users_table_uses_innodb_engine(): void
    {
        $engine = DB::table('information_schema.tables')
            ->where('table_schema', DB::connection()->getDatabaseName())
            ->where('table_name', 'users')
            ->value('engine');

        $this->assertSame('InnoDB', $engine);
    }
}
