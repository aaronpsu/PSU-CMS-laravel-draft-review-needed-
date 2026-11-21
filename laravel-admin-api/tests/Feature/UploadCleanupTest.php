<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadCleanupTest extends TestCase
{
    public function test_image_file_is_removed_when_database_insert_fails(): void
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagepng')) {
            $this->markTestSkipped('GD is required to generate the image fixture.');
        }

        Storage::fake('public');
        Schema::create('media', function (Blueprint $table): void {
            $table->increments('media_id');
        });

        $admin = new User();
        $admin->forceFill(['user_id' => 1, 'role' => 'admin']);
        $this->actingAs($admin, 'web');
        $csrfToken = $this->getJson('/api/auth/csrf')->json('csrfToken');
        $image = imagecreatetruecolor(1, 1);
        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        $this->withHeader('X-CSRF-TOKEN', $csrfToken)->postJson('/api/media', [
            'data' => 'data:image/png;base64,' . base64_encode((string) $imageData),
            'altText' => 'Test upload',
        ])->assertServerError();

        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
