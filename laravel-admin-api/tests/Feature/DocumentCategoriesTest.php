<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DocumentCategoriesTest extends TestCase
{
    private string $csrfToken;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_categories');
        Schema::create('document_categories', function (Blueprint $table): void {
            $table->increments('category_id');
            $table->string('name', 80)->unique();
            $table->string('description', 255)->nullable();
        });
        Schema::create('documents', function (Blueprint $table): void {
            $table->increments('document_id');
            $table->unsignedInteger('category_id');
        });

        $admin = new User();
        $admin->forceFill(['user_id' => 1, 'role' => 'admin']);
        $this->actingAs($admin, 'web');
        $this->csrfToken = (string) $this->getJson('/api/auth/csrf')->json('csrfToken');
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_categories');

        parent::tearDown();
    }

    public function test_document_categories_can_be_created_updated_and_deleted_when_unused(): void
    {
        $created = $this->withHeader('X-CSRF-TOKEN', $this->csrfToken)
            ->postJson('/api/document-categories', [
                'name' => ' Policies ',
                'description' => 'University policies.',
            ])->assertCreated()
            ->assertJsonPath('name', 'Policies')
            ->assertJsonPath('description', 'University policies.')
            ->assertJsonPath('documentCount', 0);

        $id = (int) $created->json('id');
        $this->withHeader('X-CSRF-TOKEN', $this->csrfToken)
            ->putJson('/api/document-categories/'.$id, [
                'name' => 'Guidelines',
                'description' => 'Updated description.',
            ])->assertOk()->assertJsonPath('name', 'Guidelines');

        $this->withHeader('X-CSRF-TOKEN', $this->csrfToken)
            ->deleteJson('/api/document-categories/'.$id)
            ->assertOk()->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('document_categories', ['category_id' => $id]);
    }

    public function test_categories_with_documents_cannot_be_deleted(): void
    {
        DB::table('document_categories')->insert(['category_id' => 1, 'name' => 'Reports']);
        DB::table('documents')->insert(['document_id' => 1, 'category_id' => 1]);

        $this->withHeader('X-CSRF-TOKEN', $this->csrfToken)
            ->deleteJson('/api/document-categories/1')
            ->assertStatus(409)
            ->assertJsonPath('documentCount', 1);

        $this->assertDatabaseHas('document_categories', ['category_id' => 1]);
    }
}
