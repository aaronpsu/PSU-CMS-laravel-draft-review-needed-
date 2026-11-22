<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InitialAdministratorRoleIdTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['users', 'campuses', 'roles'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('roles', function (Blueprint $table): void {
            $table->increments('role_id');
            $table->string('name')->unique();
        });
        Schema::create('campuses', function (Blueprint $table): void {
            $table->increments('campus_id');
            $table->string('code');
            $table->string('name');
            $table->string('address')->nullable();
            $table->boolean('is_main')->default(false);
            $table->string('status')->default('active');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->increments('user_id');
            $table->unsignedInteger('role_id');
            $table->string('role')->nullable();
            $table->unsignedInteger('campus_id');
            $table->string('username')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('full_name')->nullable();
            $table->string('email')->unique();
            $table->string('password_hash');
            $table->string('status')->default('active');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
        DB::table('roles')->insert([
            ['role_id' => 1, 'name' => 'Super Administrator'],
            ['role_id' => 2, 'name' => 'Administrator'],
        ]);
    }

    protected function tearDown(): void
    {
        foreach (['users', 'campuses', 'roles'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_initial_admin_uses_the_role_foreign_key_without_a_text_role(): void
    {
        $csrf = (string) $this->getJson('/api/auth/csrf')->json('csrfToken');
        $response = $this->withHeader('X-CSRF-TOKEN', $csrf)->postJson('/api/auth/setup', [
            'fullName' => 'Alex Rivera',
            'email' => 'alex@example.com',
            'password' => 'A-strong-password-123',
        ])->assertCreated()->assertJsonPath('user.role', 'admin');

        $this->assertDatabaseHas('users', ['role_id' => 2, 'role' => null]);
        $this->assertFalse($this->getJson('/api/auth/setup/status')->json('setupRequired'));
        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonPath('0.role', 'admin');
        $this->getJson('/api/user-roles')
            ->assertOk()
            ->assertJsonPath('0.name', 'admin');

        $this->withHeader('X-CSRF-TOKEN', (string) $response->json('csrfToken'))
            ->postJson('/api/auth/setup', [
                'fullName' => 'Second Admin',
                'email' => 'second@example.com',
                'password' => 'A-strong-password-456',
            ])->assertConflict();
    }
}
