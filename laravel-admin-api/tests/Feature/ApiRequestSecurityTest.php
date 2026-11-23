<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ApiRequestSecurityTest extends TestCase
{
    public function test_json_method_override_is_rejected(): void
    {
        $this->signInAsAdmin();

        $this->call('POST', '/api/events', ['_method' => 'DELETE'], [], [], [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_HOST' => '127.0.0.1',
            'REMOTE_ADDR' => '127.0.0.1',
        ], '_method=DELETE')
            ->assertBadRequest();
    }

    public function test_mutations_reject_payloads_over_eight_megabytes(): void
    {
        $this->signInAsAdmin();

        $this->call('POST', '/api/events', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'CONTENT_LENGTH' => (string) (8 * 1024 * 1024 + 1),
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_HOST' => '127.0.0.1',
            'REMOTE_ADDR' => '127.0.0.1',
        ], '{}')
            ->assertStatus(413);
    }

    public function test_body_size_is_limited_even_without_content_length(): void
    {
        $this->signInAsAdmin();
        $body = str_repeat('x', 8 * 1024 * 1024 + 1);

        $this->call('POST', '/api/events', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_HOST' => '127.0.0.1',
            'REMOTE_ADDR' => '127.0.0.1',
        ], $body)->assertStatus(413);
    }

    public function test_mutations_reject_malformed_content_length_headers(): void
    {
        $this->signInAsAdmin();

        $response = $this->call('POST', '/api/events', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'CONTENT_LENGTH' => 'not-a-number',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_HOST' => '127.0.0.1',
            'REMOTE_ADDR' => '127.0.0.1',
        ], '{}');

        $this->assertContains($response->getStatusCode(), [400, 413]);
    }

    public function test_mutations_allow_bounded_requests_without_content_length(): void
    {
        $this->signInAsAdmin();

        $this->call('POST', '/api/events', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_HOST' => '127.0.0.1',
            'REMOTE_ADDR' => '127.0.0.1',
        ], '{}')->assertStatus(419);
    }

    public function test_untrusted_hosts_are_rejected(): void
    {
        $this->call('GET', 'http://untrusted.example/api/auth/csrf', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_HOST' => 'untrusted.example',
            'REMOTE_ADDR' => '127.0.0.1',
        ])
            ->assertStatus(400);
    }

    public function test_authors_can_use_editorial_routes_but_not_admin_routes(): void
    {
        $author = new User();
        $author->forceFill(['user_id' => 2, 'role' => 'Author']);

        $this->actingAs($author, 'web')->getJson('/api/health')->assertOk();
        $this->getJson('/api/users')->assertForbidden();
        $this->getJson('/api/site-settings')->assertForbidden();
    }

    private function signInAsAdmin(): void
    {
        $admin = new User();
        $admin->forceFill(['user_id' => 1, 'role' => 'admin']);
        $this->actingAs($admin, 'web');
    }
}
