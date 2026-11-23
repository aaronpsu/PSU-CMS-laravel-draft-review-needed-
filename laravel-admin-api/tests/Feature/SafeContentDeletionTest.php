<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SafeContentDeletionTest extends TestCase
{
    private const REFERENCE_COLUMNS = [
        'announcements' => ['campus_id'],
        'colleges' => ['campus_id', 'logo_media_id', 'header_media_id'],
        'contact_submissions' => ['campus_id'],
        'departments' => ['campus_id'],
        'documents' => ['campus_id'],
        'events' => ['campus_id', 'cover_media_id'],
        'faculty_staff' => ['campus_id', 'photo_media_id'],
        'faqs' => ['campus_id'],
        'featured_items' => ['campus_id'],
        'feedback_submissions' => ['campus_id'],
        'homepage_section_items' => ['media_id'],
        'homepage_sections' => ['campus_id'],
        'news' => ['campus_id', 'cover_media_id'],
        'pages' => ['campus_id'],
        'program_campuses' => ['campus_id'],
        'themes' => ['campus_id', 'preview_media_id'],
        'users' => ['campus_id', 'avatar_media_id'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (array_keys(self::REFERENCE_COLUMNS) as $table) {
            Schema::dropIfExists($table);
        }
        foreach (['site_settings', 'media', 'campuses'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('campuses', function (Blueprint $table): void {
            $table->increments('campus_id');
        });
        Schema::create('media', function (Blueprint $table): void {
            $table->increments('media_id');
            $table->string('file_path')->nullable();
        });
        Schema::create('site_settings', function (Blueprint $table): void {
            $table->string('setting_key')->primary();
            $table->text('setting_value')->nullable();
        });

        foreach (self::REFERENCE_COLUMNS as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns): void {
                $table->increments('id');
                foreach ($columns as $column) {
                    $table->unsignedInteger($column)->nullable();
                }
            });
        }

        $admin = new User();
        $admin->forceFill(['user_id' => 1, 'role' => 'admin']);
        $this->actingAs($admin, 'web');
    }

    protected function tearDown(): void
    {
        foreach (array_keys(self::REFERENCE_COLUMNS) as $table) {
            Schema::dropIfExists($table);
        }
        foreach (['site_settings', 'media', 'campuses'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_campus_deletion_is_blocked_for_all_related_records(): void
    {
        DB::table('campuses')->insert(['campus_id' => 1]);
        DB::table('departments')->insert(['campus_id' => 1]);
        DB::table('homepage_sections')->insert(['campus_id' => 1]);
        DB::table('program_campuses')->insert(['campus_id' => 1]);

        $csrf = (string) $this->getJson('/api/auth/csrf')->json('csrfToken');
        $this->withHeader('X-CSRF-TOKEN', $csrf)
            ->deleteJson('/api/campuses/1')
            ->assertStatus(409)
            ->assertJsonPath('references.departments', 1)
            ->assertJsonPath('references.homepageSections', 1)
            ->assertJsonPath('references.programCampuses', 1);

        $this->assertDatabaseHas('campuses', ['campus_id' => 1]);
        $this->assertDatabaseHas('homepage_sections', ['campus_id' => 1]);
        $this->assertDatabaseHas('program_campuses', ['campus_id' => 1]);
    }

    public function test_media_deletion_is_blocked_for_content_and_settings_references(): void
    {
        DB::table('media')->insert(['media_id' => 1, 'file_path' => 'media/test.jpg']);
        DB::table('faculty_staff')->insert(['photo_media_id' => 1]);
        DB::table('homepage_section_items')->insert(['media_id' => 1]);
        DB::table('themes')->insert(['preview_media_id' => 1]);
        DB::table('users')->insert(['avatar_media_id' => 1]);
        DB::table('site_settings')->insert(['setting_key' => 'logo_media_id', 'setting_value' => '1']);

        $csrf = (string) $this->getJson('/api/auth/csrf')->json('csrfToken');
        $this->withHeader('X-CSRF-TOKEN', $csrf)
            ->deleteJson('/api/media/1')
            ->assertStatus(409)
            ->assertJsonPath('references.facultyPhotos', 1)
            ->assertJsonPath('references.homepageItems', 1)
            ->assertJsonPath('references.themePreviews', 1)
            ->assertJsonPath('references.userAvatars', 1)
            ->assertJsonPath('references.siteSettingsLogo', 1);

        $this->assertDatabaseHas('media', ['media_id' => 1]);
    }
}
