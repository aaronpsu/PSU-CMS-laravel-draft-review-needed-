<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MessageControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['feedback_submissions', 'contact_submissions', 'departments', 'campuses'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('campuses', function (Blueprint $table): void {
            $table->increments('campus_id');
            $table->string('name');
            $table->string('status')->default('active');
        });
        Schema::create('departments', function (Blueprint $table): void {
            $table->increments('department_id');
            $table->string('name');
        });
        Schema::create('contact_submissions', function (Blueprint $table): void {
            $table->increments('submission_id');
            $table->unsignedInteger('campus_id')->nullable();
            $table->unsignedInteger('department_id')->nullable();
            $table->unsignedInteger('handled_by')->nullable();
            $table->string('full_name', 150);
            $table->string('email', 150);
            $table->string('subject', 255);
            $table->text('message');
            $table->string('status', 20)->default('unread');
            $table->string('reply_subject')->nullable();
            $table->text('reply_message')->nullable();
            $table->dateTime('received_at');
            $table->dateTime('replied_at')->nullable();
        });
        Schema::create('feedback_submissions', function (Blueprint $table): void {
            $table->increments('feedback_id');
            $table->unsignedInteger('campus_id')->nullable();
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->string('full_name', 150);
            $table->string('email', 150)->nullable();
            $table->string('category', 50);
            $table->unsignedTinyInteger('rating');
            $table->text('message');
            $table->string('status', 20)->default('new');
            $table->dateTime('created_at');
        });
        DB::table('campuses')->insert(['name' => 'Main Campus', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        foreach (['feedback_submissions', 'contact_submissions', 'departments', 'campuses'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_public_inquiries_and_feedback_are_saved_and_available_to_the_admin_inbox(): void
    {
        $csrf = (string) $this->getJson('/api/auth/csrf')->json('csrfToken');
        $this->withHeader('X-CSRF-TOKEN', $csrf)->postJson('/api/contact-submissions', [
            'fullName' => 'Alex Rivera',
            'email' => 'alex@example.com',
            'subject' => 'Campus question',
            'campusId' => 1,
            'type' => 'inquiry',
            'message' => 'Please share the office hours.',
        ])->assertCreated()->assertJsonPath('reference', 'MSG-1');

        $this->withHeader('X-CSRF-TOKEN', $csrf)->postJson('/api/contact-submissions', [
            'fullName' => 'Jamie Cruz',
            'email' => 'jamie@example.com',
            'subject' => 'Website feedback',
            'campusId' => 1,
            'type' => 'feedback',
            'rating' => 5,
            'category' => 'Website',
            'message' => 'The new site is easy to use.',
        ])->assertCreated()->assertJsonPath('reference', 'FB-1');

        $this->assertDatabaseHas('contact_submissions', ['email' => 'alex@example.com', 'status' => 'unread']);
        $this->assertDatabaseHas('feedback_submissions', ['email' => 'jamie@example.com', 'rating' => 5, 'status' => 'new']);

        $admin = new User();
        $admin->forceFill(['user_id' => 1, 'role' => 'admin']);
        $inbox = $this->actingAs($admin, 'web')->getJson('/api/messages')
            ->assertOk()->assertJsonCount(2, 'data');
        $inbox->assertJsonFragment(['type' => 'feedback']);
        $inbox->assertJsonFragment(['type' => 'inquiry']);
    }

    public function test_contact_status_and_reply_updates_are_persisted(): void
    {
        DB::table('contact_submissions')->insert([
            'full_name' => 'Alex Rivera',
            'email' => 'alex@example.com',
            'subject' => 'Campus question',
            'message' => 'Please share the office hours.',
            'status' => 'unread',
            'received_at' => now(),
        ]);
        $admin = new User();
        $admin->forceFill(['user_id' => 1, 'role' => 'admin']);
        $this->actingAs($admin, 'web');
        $csrf = (string) $this->getJson('/api/auth/csrf')->json('csrfToken');

        $this->withHeader('X-CSRF-TOKEN', $csrf)->putJson('/api/messages/c-1', [
            'reply' => ['subject' => 'Re: Campus question', 'body' => 'The office is open on weekdays.'],
        ])->assertOk()->assertJsonPath('status', 'responded')
            ->assertJsonPath('reply.subject', 'Re: Campus question')
            ->assertJsonPath('reply.body', 'The office is open on weekdays.');

        $this->assertDatabaseHas('contact_submissions', [
            'submission_id' => 1,
            'status' => 'responded',
            'reply_subject' => 'Re: Campus question',
            'reply_message' => 'The office is open on weekdays.',
        ]);
    }
}
