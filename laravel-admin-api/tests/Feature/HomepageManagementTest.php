<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomepageManagementTest extends TestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        foreach (['homepage_featured_items', 'homepage_banners', 'homepage_settings', 'news'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('homepage_settings', function (Blueprint $table): void {
            $table->bigIncrements('setting_id');
            $table->string('setting_key', 100)->unique();
            $table->longText('setting_value')->nullable();
            $table->timestamps();
        });
        Schema::create('homepage_banners', function (Blueprint $table): void {
            $table->bigIncrements('banner_id');
            $table->string('title');
            $table->text('subtitle')->nullable();
            $table->string('image_path', 500);
            $table->string('button_label', 100)->nullable();
            $table->string('button_url', 2048)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('homepage_featured_items', function (Blueprint $table): void {
            $table->bigIncrements('featured_item_id');
            $table->string('content_type');
            $table->unsignedInteger('content_id');
            $table->string('section_title')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('news', function (Blueprint $table): void {
            $table->increments('news_id');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        foreach (['homepage_featured_items', 'homepage_banners', 'homepage_settings', 'news'] as $table) {
            Schema::dropIfExists($table);
        }
        parent::tearDown();
    }

    public function test_banner_can_be_created_from_json_listed_updated_and_deleted(): void
    {
        $csrf = $this->signInAsAdmin();

        $created = $this->withHeader('X-CSRF-TOKEN', $csrf)->postJson('/api/homepage/banners', [
            'title' => 'Enrollment Now Open',
            'subtitle' => 'Apply today',
            'button_url' => '/apply',
            'is_active' => true,
            'image' => 'data:image/png;base64,'.self::PNG,
        ])->assertCreated()->assertJsonPath('data.title', 'Enrollment Now Open')->assertJsonPath('data.is_active', true);

        $id = $created->json('data.banner_id');
        $this->assertNotEmpty($created->json('data.image_url'));
        $this->assertArrayNotHasKey('image_path', $created->json('data'));
        $this->assertCount(1, Storage::disk('public')->allFiles('homepage/banners'));

        $this->getJson('/api/homepage/banners')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/homepage/banners/{$id}/image")->assertOk();

        $this->withHeader('X-CSRF-TOKEN', $csrf)->putJson("/api/homepage/banners/{$id}", ['is_active' => false, 'title' => 'Closed'])
            ->assertOk()->assertJsonPath('data.is_active', false)->assertJsonPath('data.title', 'Closed');

        $this->withHeader('X-CSRF-TOKEN', $csrf)->deleteJson("/api/homepage/banners/{$id}")->assertOk();
        $this->assertCount(0, Storage::disk('public')->allFiles('homepage/banners'));
    }

    public function test_banner_rejects_bad_images_and_unsafe_links(): void
    {
        $csrf = $this->signInAsAdmin();

        $this->withHeader('X-CSRF-TOKEN', $csrf)->postJson('/api/homepage/banners', [
            'title' => 'x', 'image' => 'data:text/html;base64,'.base64_encode('<script>1</script>'),
        ])->assertStatus(422);

        $this->withHeader('X-CSRF-TOKEN', $csrf)->postJson('/api/homepage/banners', [
            'title' => 'x', 'button_url' => 'javascript:alert(1)', 'image' => 'data:image/png;base64,'.self::PNG,
        ])->assertStatus(422)->assertJsonValidationErrors('button_url');

        $this->assertDatabaseCount('homepage_banners', 0);
    }

    public function test_multipart_uploads_are_not_the_supported_path(): void
    {
        $csrf = $this->signInAsAdmin();

        $this->withHeader('X-CSRF-TOKEN', $csrf)->post('/api/homepage/banners', ['title' => 'x'], ['Accept' => 'application/json'])
            ->assertStatus(415);
    }

    public function test_settings_are_saved_and_about_html_is_sanitised(): void
    {
        $csrf = $this->signInAsAdmin();

        $this->withHeader('X-CSRF-TOKEN', $csrf)->putJson('/api/homepage/settings', ['settings' => [
            'welcome_heading' => 'Welcome',
            'featured_content' => 'upcoming-events',
            'about_psu' => '<p onclick="x()">Hi</p><script>alert(1)</script><a href="javascript:alert(1)">bad</a>',
        ]])->assertOk()->assertJsonPath('data.welcome_heading', 'Welcome');

        $about = $this->getJson('/api/homepage/settings')->json('data.about_psu');
        $this->assertStringNotContainsString('<script', $about);
        $this->assertStringNotContainsString('javascript:', $about);
        $this->assertStringNotContainsString('onclick', $about);

        $this->withHeader('X-CSRF-TOKEN', $csrf)->putJson('/api/homepage/settings', ['settings' => ['featured_content' => 'nope']])
            ->assertStatus(422);
        $this->withHeader('X-CSRF-TOKEN', $csrf)->putJson('/api/homepage/settings', ['settings' => ['unknown' => 'x']])
            ->assertStatus(422);
    }

    public function test_featured_items_require_published_content_and_keep_order(): void
    {
        $csrf = $this->signInAsAdmin();
        DB::table('news')->insert([
            ['title' => 'One', 'status' => 'published', 'published_at' => '2026-10-01 00:00:00'],
            ['title' => 'Two', 'status' => 'published', 'published_at' => '2026-10-02 00:00:00'],
            ['title' => 'Draft', 'status' => 'draft', 'published_at' => null],
        ]);

        $this->getJson('/api/homepage/content/news')->assertOk()->assertJsonCount(2, 'data');

        $this->withHeader('X-CSRF-TOKEN', $csrf)->putJson('/api/homepage/featured', ['items' => [
            ['content_type' => 'news', 'content_id' => 3],
        ]])->assertStatus(422);

        $this->withHeader('X-CSRF-TOKEN', $csrf)->putJson('/api/homepage/featured', ['items' => [
            ['content_type' => 'news', 'content_id' => 2, 'sort_order' => 0],
            ['content_type' => 'news', 'content_id' => 1, 'sort_order' => 1],
        ]])->assertOk()->assertJsonPath('data.0.content.title', 'Two')->assertJsonPath('data.1.content.title', 'One');
    }

    public function test_public_homepage_only_lists_active_banners(): void
    {
        $csrf = $this->signInAsAdmin();
        foreach ([true, false] as $active) {
            $this->withHeader('X-CSRF-TOKEN', $csrf)->postJson('/api/homepage/banners', [
                'title' => $active ? 'Shown' : 'Hidden', 'is_active' => $active, 'image' => 'data:image/png;base64,'.self::PNG,
            ])->assertCreated();
        }

        $this->getJson('/api/homepage/public')->assertOk()
            ->assertJsonCount(1, 'data.banners')->assertJsonPath('data.banners.0.title', 'Shown');
    }

    private function signInAsAdmin(): string
    {
        $admin = new User();
        $admin->forceFill(['user_id' => 1, 'role' => 'admin']);
        $this->actingAs($admin, 'web');

        $response = $this->getJson('/api/auth/csrf');
        $response->assertOk();

        return (string) $response->json('csrfToken');
    }
}
