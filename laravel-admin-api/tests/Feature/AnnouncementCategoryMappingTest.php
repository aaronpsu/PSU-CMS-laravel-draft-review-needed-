<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AnnouncementCategoryMappingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['announcements', 'announcement_categories', 'documents', 'campuses', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('users', function (Blueprint $table): void {
            $table->increments('user_id');
            $table->string('full_name');
        });
        Schema::create('campuses', function (Blueprint $table): void {
            $table->increments('campus_id');
            $table->string('name');
        });
        Schema::create('documents', function (Blueprint $table): void {
            $table->increments('document_id');
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('file_size')->nullable();
        });
        Schema::create('announcement_categories', function (Blueprint $table): void {
            $table->increments('category_id');
            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
        });
        Schema::create('announcements', function (Blueprint $table): void {
            $table->increments('announcement_id');
            $table->unsignedInteger('campus_id')->nullable();
            $table->unsignedInteger('department_id')->nullable();
            $table->unsignedInteger('category_id');
            $table->unsignedInteger('created_by');
            $table->string('title');
            $table->string('author_name')->nullable();
            $table->text('content')->nullable();
            $table->string('priority')->default('normal');
            $table->dateTime('published_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->unsignedInteger('attachment_document_id')->nullable();
            $table->string('status')->default('draft');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
        DB::table('users')->insert(['user_id' => 1, 'full_name' => 'Admin User']);
        DB::table('announcement_categories')->insert([
            'category_id' => 1,
            'name' => 'Academic Notice',
            'slug' => 'academic-notice',
        ]);
    }

    protected function tearDown(): void
    {
        foreach (['announcements', 'announcement_categories', 'documents', 'campuses', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_announcement_category_is_stored_in_its_own_category_foreign_key(): void
    {
        $admin = new User();
        $admin->forceFill(['user_id' => 1, 'role' => 'admin', 'full_name' => 'Admin User']);
        $this->actingAs($admin, 'web');
        $csrf = (string) $this->getJson('/api/auth/csrf')->json('csrfToken');

        $this->withHeader('X-CSRF-TOKEN', $csrf)->postJson('/api/announcements', [
            'title' => 'Enrollment Advisory',
            'categoryId' => 1,
            'priority' => 'normal',
            'status' => 'draft',
            'content' => 'The enrollment schedule is available.',
        ])->assertCreated()
            ->assertJsonPath('categoryId', '1')
            ->assertJsonPath('category', 'Academic Notice');

        $this->assertDatabaseHas('announcements', ['category_id' => 1, 'title' => 'Enrollment Advisory']);
    }
}
