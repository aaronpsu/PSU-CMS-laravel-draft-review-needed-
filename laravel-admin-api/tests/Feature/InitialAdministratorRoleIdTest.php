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

    public function test_unauthenticated_api_requests_return_json_without_an_accept_header(): void
    {
        $this->get('/api/auth/me')->assertUnauthorized();
        $this->get('/api/users')->assertUnauthorized();
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
            ->assertJsonPath('0.role', 'Administrator')
            ->assertJsonPath('0.roleId', 2);
        $this->getJson('/api/user-roles')
            ->assertOk()
            ->assertJsonPath('0.name', 'Administrator')
            ->assertJsonPath('0.roleId', 2)
            ->assertJsonPath('0.userCount', 1)
            ->assertJsonPath('0.canRename', false)
            ->assertJsonPath('1.name', 'Super Administrator')
            ->assertJsonPath('1.userCount', 0)
            ->assertJsonPath('1.canRename', false);

        $this->withHeader('X-CSRF-TOKEN', (string) $response->json('csrfToken'))
            ->putJson('/api/user-roles', [
                'currentName' => 'Administrator',
                'name' => 'Site Manager',
            ])->assertUnprocessable();
        $this->assertDatabaseHas('roles', ['role_id' => 2, 'name' => 'Administrator']);

        $this->withHeader('X-CSRF-TOKEN', (string) $response->json('csrfToken'))
            ->postJson('/api/auth/setup', [
                'fullName' => 'Second Admin',
                'email' => 'second@example.com',
                'password' => 'A-strong-password-456',
            ])->assertConflict();
    }

    public function test_user_management_assigns_and_filters_by_role_id(): void
    {
        $csrf = (string) $this->getJson('/api/auth/csrf')->json('csrfToken');
        $setup = $this->withHeader('X-CSRF-TOKEN', $csrf)->postJson('/api/auth/setup', [
            'fullName' => 'Alex Rivera',
            'email' => 'alex@example.com',
            'password' => 'A-strong-password-123',
        ])->assertCreated();

        $this->withHeader('X-CSRF-TOKEN', (string) $setup->json('csrfToken'))
            ->postJson('/api/users', [
                'fullName' => 'Super Admin User',
                'email' => 'super-admin@example.com',
                'password' => 'Another-strong-password-123',
                'roleId' => 1,
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath('roleId', 1)
            ->assertJsonPath('role', 'Super Administrator');

        $this->assertDatabaseHas('users', ['email' => 'super-admin@example.com', 'role_id' => 1, 'role' => null]);
        $this->getJson('/api/users?roleId=1')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.roleId', 1);
        $this->getJson('/api/user-roles')
            ->assertOk()
            ->assertJsonPath('1.userCount', 1);
    }

    public function test_last_active_admin_cannot_be_demoted_or_deactivated(): void
    {
        $csrf = (string) $this->getJson('/api/auth/csrf')->json('csrfToken');
        $setup = $this->withHeader('X-CSRF-TOKEN', $csrf)->postJson('/api/auth/setup', [
            'fullName' => 'Alex Rivera',
            'email' => 'alex@example.com',
            'password' => 'A-strong-password-123',
        ])->assertCreated();
        DB::table('roles')->insert(['role_id' => 3, 'name' => 'Editor']);
        DB::table('users')->where('user_id', 1)->update(['role' => 'Administrator']);
        $csrf = (string) $setup->json('csrfToken');

        $this->withHeader('X-CSRF-TOKEN', $csrf)->putJson('/api/users/1', [
            'fullName' => 'Alex Rivera',
            'email' => 'alex@example.com',
            'roleId' => 3,
            'status' => 'active',
        ])->assertUnprocessable();
        $this->withHeader('X-CSRF-TOKEN', $csrf)->putJson('/api/users/1', [
            'fullName' => 'Alex Rivera',
            'email' => 'alex@example.com',
            'roleId' => 2,
            'status' => 'inactive',
        ])->assertUnprocessable();
        $this->assertDatabaseHas('users', ['user_id' => 1, 'role_id' => 2, 'status' => 'active']);

        $this->withHeader('X-CSRF-TOKEN', $csrf)->postJson('/api/users', [
            'fullName' => 'Second Admin',
            'email' => 'second@example.com',
            'password' => 'Another-strong-password-123',
            'roleId' => 1,
            'status' => 'active',
        ])->assertCreated();

        $this->withHeader('X-CSRF-TOKEN', $csrf)->putJson('/api/users/1', [
            'fullName' => 'Alex Rivera',
            'email' => 'alex@example.com',
            'roleId' => 3,
            'status' => 'active',
        ])->assertOk();
        $this->assertDatabaseHas('users', ['user_id' => 1, 'role_id' => 3, 'status' => 'active']);

        $supervisor = new \App\Models\User();
        $supervisor->forceFill(['user_id' => 99, 'role' => 'admin']);
        $this->actingAs($supervisor, 'web');
        $deleteCsrf = (string) $this->getJson('/api/auth/csrf')->json('csrfToken');
        $this->withHeader('X-CSRF-TOKEN', $deleteCsrf)
            ->deleteJson('/api/users/2')
            ->assertUnprocessable();
        $this->assertDatabaseHas('users', ['user_id' => 2, 'role_id' => 1, 'status' => 'active']);
    }
}
