<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventCategorySearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['event_categories', 'news_categories', 'announcement_categories', 'document_categories', 'campuses', 'colleges', 'departments', 'programs', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('event_categories', function (Blueprint $table): void {
            $table->increments('category_id');
            $table->string('name');
            $table->text('description')->nullable();
        });
        Schema::create('news_categories', function (Blueprint $table): void {
            $table->increments('category_id');
            $table->string('name');
        });
        Schema::create('announcement_categories', function (Blueprint $table): void {
            $table->increments('category_id');
            $table->string('name');
        });
        Schema::create('document_categories', function (Blueprint $table): void {
            $table->increments('category_id');
            $table->string('name', 80);
            $table->string('description', 255)->nullable();
        });
        Schema::create('campuses', function (Blueprint $table): void {
            $table->increments('campus_id');
            $table->string('name');
            $table->string('status');
        });
        Schema::create('colleges', function (Blueprint $table): void {
            $table->increments('college_id');
            $table->string('name');
            $table->string('code');
            $table->string('status');
        });
        Schema::create('departments', function (Blueprint $table): void {
            $table->increments('department_id');
            $table->unsignedInteger('college_id');
            $table->string('name');
        });
        Schema::create('programs', function (Blueprint $table): void {
            $table->increments('program_id');
            $table->string('degree_level')->nullable();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->increments('user_id');
            $table->string('role')->nullable();
        });
        DB::table('event_categories')->insert([
            ['category_id' => 1, 'name' => 'Research Forum', 'description' => 'Student conference'],
            ['category_id' => 2, 'name' => 'Campus Life', 'description' => 'Research support events'],
            ['category_id' => 3, 'name' => 'Athletics', 'description' => 'Sports events'],
        ]);

        $admin = new User();
        $admin->forceFill(['user_id' => 1, 'role' => 'admin']);
        $this->actingAs($admin, 'web');
    }

    protected function tearDown(): void
    {
        foreach (['event_categories', 'news_categories', 'announcement_categories', 'document_categories', 'campuses', 'colleges', 'departments', 'programs', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_event_category_search_filters_names_and_descriptions(): void
    {
        $this->getJson('/api/event-categories?q=research')->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.name', 'Campus Life')
            ->assertJsonPath('1.name', 'Research Forum');
    }

    public function test_options_includes_event_categories_without_server_error(): void
    {
        $this->getJson('/api/options')->assertOk()
            ->assertJsonPath('eventCategories.0.name', 'Athletics')
            ->assertJsonPath('eventCategories.0.id', 3);
    }
}
