<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsExcerptCompatibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['news_category_map', 'news', 'news_categories', 'media', 'campuses', 'users'] as $table) {
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
        Schema::create('media', function (Blueprint $table): void {
            $table->increments('media_id');
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_type')->nullable();
            $table->unsignedInteger('file_size_kb')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('uploaded_by')->nullable();
        });
        Storage::fake('public');
        Schema::create('news_categories', function (Blueprint $table): void {
            $table->increments('category_id');
            $table->string('name');
        });
        Schema::create('news', function (Blueprint $table): void {
            $table->increments('news_id');
            $table->unsignedInteger('campus_id')->nullable();
            $table->unsignedInteger('department_id')->nullable();
            $table->unsignedInteger('cover_media_id')->nullable();
            $table->unsignedInteger('created_by');
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('author_name')->nullable();
            $table->string('excerpt', 500)->nullable();
            $table->text('content')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->string('status')->default('draft');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
        Schema::create('news_category_map', function (Blueprint $table): void {
            $table->unsignedInteger('news_id');
            $table->unsignedInteger('category_id');
            $table->primary(['news_id', 'category_id']);
        });
        DB::table('users')->insert(['user_id' => 1, 'full_name' => 'Admin User']);
        DB::table('news_categories')->insert(['category_id' => 1, 'name' => 'Campus Life']);
    }

    protected function tearDown(): void
    {
        foreach (['news_category_map', 'news', 'news_categories', 'media', 'campuses', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_news_is_created_and_listed_using_the_sql_dump_excerpt_column(): void
    {
        $admin = new User();
        $admin->forceFill(['user_id' => 1, 'role' => 'admin']);
        $this->actingAs($admin, 'web');
        $csrf = (string) $this->getJson('/api/auth/csrf')->json('csrfToken');

        $this->withHeader('X-CSRF-TOKEN', $csrf)->postJson('/api/news', [
            'title' => 'Campus Update',
            'categoryId' => 1,
            'author' => 'Admin User',
            'shortDescription' => 'A short summary.',
            'content' => 'The full article.',
            'status' => 'draft',
        ])->assertCreated()->assertJsonPath('shortDescription', 'A short summary.');

        $this->getJson('/api/news')->assertOk()
            ->assertJsonPath('0.shortDescription', 'A short summary.')
            ->assertJsonPath('0.category', 'Campus Life');
    }

    public function test_news_cover_image_is_available_in_the_listing_and_preview_route(): void
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagejpeg')) {
            $this->markTestSkipped('GD is required to process uploaded images.');
        }

        $admin = new User();
        $admin->forceFill(['user_id' => 1, 'role' => 'admin']);
        $this->actingAs($admin, 'web');
        $csrf = (string) $this->getJson('/api/auth/csrf')->json('csrfToken');

        $image = imagecreatetruecolor(640, 480);
        ob_start();
        imagejpeg($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        $created = $this->withHeader('X-CSRF-TOKEN', $csrf)->postJson('/api/news', [
            'title' => 'Image Preview Test',
            'categoryId' => 1,
            'author' => 'Admin User',
            'shortDescription' => 'Image preview test.',
            'content' => 'Article body.',
            'status' => 'published',
            'image' => 'data:image/jpeg;base64,' . base64_encode((string) $bytes),
        ])->assertCreated();

        $imageUrl = (string) $created->json('image');
        $this->assertStringContainsString('/api/media/', $imageUrl);
        $this->assertSame($imageUrl, $this->getJson('/api/news')->json('0.image'));

        $mediaId = (int) DB::table('news')->where('title', 'Image Preview Test')->value('cover_media_id');
        $preview = $this->get('/api/media/' . $mediaId)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
        ob_start();
        $preview->baseResponse->sendContent();
        $this->assertNotFalse(getimagesizefromstring((string) ob_get_clean()));
    }
}
