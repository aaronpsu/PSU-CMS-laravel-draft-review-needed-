<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SiteSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('site_settings');
        Schema::create('site_settings', function (Blueprint $table): void {
            $table->string('setting_key')->primary();
            $table->text('setting_value')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('site_settings');

        parent::tearDown();
    }

    public function test_site_settings_can_be_read_and_saved(): void
    {
        $csrfToken = $this->signInAsAdmin();
        DB::table('site_settings')->insert([
            'setting_key' => 'website_name',
            'setting_value' => 'Old name',
        ]);

        $this->withHeader('X-CSRF-TOKEN', $csrfToken)->putJson('/api/site-settings', [
            'settings' => [
                'website_name' => 'PSU Campus',
                'default_language' => 'Filipino',
                'time_zone' => 'Asia/Manila',
            ],
        ])->assertOk()
            ->assertJsonPath('data.website_name', 'PSU Campus')
            ->assertJsonPath('data.default_language', 'Filipino');

        $this->withHeader('X-CSRF-TOKEN', $csrfToken)->getJson('/api/site-settings')
            ->assertOk()
            ->assertJsonPath('data.website_name', 'PSU Campus');
    }

    public function test_unsupported_site_settings_are_rejected(): void
    {
        $csrfToken = $this->signInAsAdmin();

        $this->withHeader('X-CSRF-TOKEN', $csrfToken)->putJson('/api/site-settings', [
            'settings' => ['admin_password' => 'not allowed'],
        ])->assertUnprocessable();

        $this->assertDatabaseCount('site_settings', 0);
    }

    public function test_website_layout_settings_are_saved_as_database_settings(): void
    {
        $csrfToken = $this->signInAsAdmin();
        $order = ['banner', 'featured_news', 'announcements', 'events', 'about'];

        $this->withHeader('X-CSRF-TOKEN', $csrfToken)->putJson('/api/site-settings', [
            'settings' => [
                'header_layout' => 'logo-left',
                'footer_layout' => 'two-columns',
                'menu_layout' => 'compact',
                'homepage_section_order' => $order,
            ],
        ])->assertOk()
            ->assertJsonPath('data.header_layout', 'logo-left')
            ->assertJsonPath('data.footer_layout', 'two-columns')
            ->assertJsonPath('data.menu_layout', 'compact')
            ->assertJsonPath('data.homepage_section_order', $order);

        $this->assertDatabaseHas('site_settings', [
            'setting_key' => 'homepage_section_order',
            'setting_value' => json_encode($order),
        ]);
    }

    public function test_invalid_website_layout_section_order_is_rejected(): void
    {
        $csrfToken = $this->signInAsAdmin();

        $this->withHeader('X-CSRF-TOKEN', $csrfToken)->putJson('/api/site-settings', [
            'settings' => [
                'homepage_section_order' => ['banner', 'banner', 'announcements', 'events', 'about'],
            ],
        ])->assertUnprocessable();

        $this->assertDatabaseCount('site_settings', 0);
    }

    public function test_site_settings_require_an_authenticated_administrator(): void
    {
        $this->getJson('/api/site-settings')->assertUnauthorized();

        $editor = new User();
        $editor->forceFill(['user_id' => 2, 'role' => 'editor']);
        $this->actingAs($editor, 'web')
            ->getJson('/api/site-settings')
            ->assertForbidden();
    }

    public function test_admin_mutations_require_a_valid_csrf_header(): void
    {
        $this->signInAsAdmin();

        $this->putJson('/api/site-settings', [
            'settings' => ['website_name' => 'Blocked update'],
        ])->assertStatus(419);

        $this->assertDatabaseCount('site_settings', 0);
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
