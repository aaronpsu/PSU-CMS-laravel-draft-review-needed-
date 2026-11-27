<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FacultyDirectoryTest extends TestCase
{
    private string $csrfToken;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['faculty_staff', 'departments', 'colleges', 'campuses'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('campuses', function (Blueprint $table): void {
            $table->increments('campus_id');
            $table->string('name');
        });
        Schema::create('colleges', function (Blueprint $table): void {
            $table->increments('college_id');
            $table->string('name');
        });
        Schema::create('departments', function (Blueprint $table): void {
            $table->increments('department_id');
            $table->unsignedInteger('college_id')->nullable();
            $table->unsignedInteger('campus_id')->nullable();
            $table->string('name');
        });
        Schema::create('faculty_staff', function (Blueprint $table): void {
            $table->increments('faculty_id');
            $table->unsignedInteger('campus_id');
            $table->unsignedInteger('department_id')->nullable();
            $table->unsignedInteger('photo_media_id')->nullable();
            $table->string('first_name', 80);
            $table->string('middle_name', 80)->nullable();
            $table->string('last_name', 80);
            $table->string('position', 150)->nullable();
            $table->string('email', 150)->nullable()->unique();
            $table->string('contact_number', 30)->nullable();
            $table->text('educational_background')->nullable();
            $table->text('biography')->nullable();
            $table->string('status', 20)->default('active');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
        DB::table('campuses')->insert([
            ['campus_id' => 1, 'name' => 'Lingayen'],
            ['campus_id' => 2, 'name' => 'Urdaneta'],
        ]);
        DB::table('colleges')->insert(['college_id' => 1, 'name' => 'College of Computing']);
        DB::table('departments')->insert([
            'department_id' => 1,
            'college_id' => 1,
            'campus_id' => 1,
            'name' => 'Information Technology',
        ]);

        $admin = new User();
        $admin->forceFill(['user_id' => 1, 'role' => 'admin']);
        $this->actingAs($admin, 'web');
        $this->csrfToken = (string) $this->getJson('/api/auth/csrf')->json('csrfToken');
    }

    protected function tearDown(): void
    {
        foreach (['faculty_staff', 'departments', 'colleges', 'campuses'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_personnel_records_save_the_fields_supported_by_the_schema(): void
    {
        $this->withHeader('X-CSRF-TOKEN', $this->csrfToken)->postJson('/api/faculty', [
            'firstName' => 'Ana',
            'middleName' => 'M',
            'lastName' => 'Reyes',
            'position' => 'Professor',
            'campusId' => 1,
            'departmentId' => 1,
            'email' => 'ana.reyes@example.edu',
            'contactNumber' => '+63 912 345 6789',
            'education' => 'PhD in Information Technology',
            'biography' => 'Faculty biography.',
            'status' => 'active',
        ])->assertCreated()
            ->assertJsonPath('name', 'Ana M Reyes')
            ->assertJsonPath('college', 'College of Computing')
            ->assertJsonPath('education', 'PhD in Information Technology');

        $this->assertDatabaseHas('faculty_staff', [
            'first_name' => 'Ana',
            'educational_background' => 'PhD in Information Technology',
            'contact_number' => '+63 912 345 6789',
        ]);

        $this->getJson('/api/faculty?college=1&status=active')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.department', 'Information Technology');
    }

    public function test_personnel_cannot_be_assigned_to_a_department_on_another_campus(): void
    {
        $this->withHeader('X-CSRF-TOKEN', $this->csrfToken)->postJson('/api/faculty', [
            'firstName' => 'Ana',
            'lastName' => 'Reyes',
            'position' => 'Professor',
            'campusId' => 2,
            'departmentId' => 1,
            'status' => 'active',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('faculty_staff', 0);
    }
}
