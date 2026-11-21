<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InitialMigrationSafetyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_existing_cms_tables_are_preserved_and_missing_session_table_is_created(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->primary();
            $table->string('full_name');
        });
        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
        });

        $migration = require database_path('migrations/0001_01_01_000000_create_users_table.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('users', 'user_id'));
        $this->assertFalse(Schema::hasColumn('users', 'name'));
        $this->assertTrue(Schema::hasTable('sessions'));

        $migration->down();

        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('password_reset_tokens'));
        $this->assertTrue(Schema::hasTable('sessions'));
    }
}
