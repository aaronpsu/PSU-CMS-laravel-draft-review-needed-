<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ResourceUploadProcessingTest extends TestCase
{
    private string $csrfToken;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['document_versions', 'documents', 'document_categories', 'media'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('media', function (Blueprint $table): void {
            $table->increments('media_id');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('file_size_kb')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('title')->nullable();
            $table->string('alt_text')->nullable();
            $table->string('caption')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('uploaded_by')->nullable();
            $table->dateTime('created_at')->nullable();
        });
        Schema::create('document_categories', function (Blueprint $table): void {
            $table->increments('category_id');
            $table->string('name');
            $table->string('description')->nullable();
        });
        Schema::create('documents', function (Blueprint $table): void {
            $table->increments('document_id');
            $table->unsignedInteger('category_id')->nullable();
            $table->unsignedInteger('created_by');
            $table->string('title')->nullable();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->text('description')->nullable();
            $table->string('access_level')->default('public');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
        Schema::create('document_versions', function (Blueprint $table): void {
            $table->increments('version_id');
            $table->unsignedInteger('document_id');
            $table->unsignedInteger('uploaded_by');
            $table->unsignedInteger('version_no');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type');
            $table->unsignedInteger('file_size_kb');
            $table->dateTime('created_at')->nullable();
        });
        DB::table('document_categories')->insert([
            ['category_id' => 1, 'name' => 'Reports'],
            ['category_id' => 2, 'name' => 'Forms'],
        ]);

        Storage::fake('public');
        Storage::fake('local');
        $admin = new User();
        $admin->forceFill(['user_id' => 1, 'role' => 'admin']);
        $this->actingAs($admin, 'web');
        $this->csrfToken = (string) $this->getJson('/api/auth/csrf')->json('csrfToken');
    }

    public function test_image_upload_is_resized_reencoded_and_stored(): void
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagepng')) {
            $this->markTestSkipped('GD is required to process uploaded images.');
        }

        $image = imagecreatetruecolor(1600, 800);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        $response = $this->withHeader('X-CSRF-TOKEN', $this->csrfToken)->postJson('/api/media', [
            'data' => 'data:image/png;base64,' . base64_encode((string) $bytes),
            'altText' => 'Campus building',
        ])->assertCreated()
            ->assertJsonPath('mimeType', 'image/png')
            ->assertJsonPath('available', true);

        $record = DB::table('media')->where('media_id', $response->json('id'))->first();
        $path = substr($record->file_path, strlen('/storage/'));
        $stored = Storage::disk('public')->get($path);
        $dimensions = getimagesizefromstring($stored);

        $this->assertSame([1200, 600], [$dimensions[0], $dimensions[1]]);
        $this->assertSame(strlen($stored), (int) $record->file_size);
        $this->assertSame('Campus building', $record->alt_text);

        $preview = $this->get('/api/media/' . $record->media_id)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
        ob_start();
        $preview->baseResponse->sendContent();
        $previewBytes = ob_get_clean();
        $this->assertNotFalse(getimagesizefromstring((string) $previewBytes));
    }

    public function test_pdf_document_upload_is_stored_and_versioned(): void
    {
        $bytes = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
        $response = $this->withHeader('X-CSRF-TOKEN', $this->csrfToken)->postJson('/api/documents', [
            'fileName' => 'report.pdf',
            'title' => 'Annual report',
            'data' => 'data:application/pdf;base64,' . base64_encode($bytes),
        ])->assertCreated()
            ->assertJsonPath('title', 'Annual report')
            ->assertJsonPath('mimeType', 'application/pdf')
            ->assertJsonPath('available', true);

        $document = DB::table('documents')->where('document_id', $response->json('id'))->first();
        $path = substr($document->file_path, strlen('private/'));

        Storage::disk('local')->assertExists($path);
        $this->assertSame($bytes, Storage::disk('local')->get($path));
        $this->assertDatabaseHas('document_versions', [
            'document_id' => $document->document_id,
            'version_no' => 1,
            'file_type' => 'application/pdf',
        ]);
        $download = $this->get('/api/documents/' . $document->document_id)
            ->assertOk()
            ->assertDownload('report.pdf');
        ob_start();
        $download->baseResponse->sendContent();
        $downloadedBytes = ob_get_clean();
        $this->assertSame($bytes, $downloadedBytes);

        $inline = $this->get('/api/documents/' . $document->document_id . '?download=0')
            ->assertOk();
        $this->assertFalse($inline->baseResponse->headers->has('Content-Disposition'));
    }

    public function test_document_metadata_and_category_are_saved_with_the_upload(): void
    {
        $bytes = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
        $response = $this->withHeader('X-CSRF-TOKEN', $this->csrfToken)->postJson('/api/documents', [
            'fileName' => 'schedule.pdf',
            'title' => 'Academic schedule',
            'categoryId' => 2,
            'description' => 'Current academic schedule.',
            'data' => 'data:application/pdf;base64,' . base64_encode($bytes),
        ])->assertCreated()
            ->assertJsonPath('categoryId', 2)
            ->assertJsonPath('categoryName', 'Forms')
            ->assertJsonPath('description', 'Current academic schedule.');

        $this->assertDatabaseHas('documents', [
            'document_id' => $response->json('id'),
            'category_id' => 2,
            'description' => 'Current academic schedule.',
        ]);
    }

    public function test_document_can_be_uploaded_and_reassigned_as_uncategorized(): void
    {
        $bytes = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
        $response = $this->withHeader('X-CSRF-TOKEN', $this->csrfToken)->postJson('/api/documents', [
            'fileName' => 'uncategorized.pdf',
            'data' => 'data:application/pdf;base64,' . base64_encode($bytes),
        ])->assertCreated()
            ->assertJsonPath('categoryId', null)
            ->assertJsonPath('categoryName', 'Uncategorized');

        $documentId = (int) $response->json('id');
        $this->assertDatabaseHas('documents', [
            'document_id' => $documentId,
            'category_id' => null,
        ]);

        $this->withHeader('X-CSRF-TOKEN', $this->csrfToken)
            ->putJson('/api/documents/' . $documentId, ['categoryId' => 2])
            ->assertOk()
            ->assertJsonPath('categoryId', 2);

        $this->withHeader('X-CSRF-TOKEN', $this->csrfToken)
            ->putJson('/api/documents/' . $documentId, ['categoryId' => null])
            ->assertOk()
            ->assertJsonPath('categoryId', null)
            ->assertJsonPath('categoryName', 'Uncategorized');

        $this->assertDatabaseHas('documents', [
            'document_id' => $documentId,
            'category_id' => null,
        ]);
    }

    public function test_docx_upload_is_validated_as_openxml_instead_of_generic_zip(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('The PHP ZIP extension is required to validate Office Open XML files.');
        }

        $path = tempnam(sys_get_temp_dir(), 'cms-docx-');
        $this->assertNotFalse($path);
        $archive = new ZipArchive();
        try {
            $this->assertSame(true, $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
            $archive->addFromString(
                '[Content_Types].xml',
                '<?xml version="1.0"?><Types><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>'
            );
            $archive->addFromString('word/document.xml', '<w:document xmlns:w="urn:test"/>');
            $this->assertTrue($archive->close());
            $bytes = file_get_contents($path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $response = $this->withHeader('X-CSRF-TOKEN', $this->csrfToken)->postJson('/api/documents', [
            'fileName' => 'minutes.docx',
            'data' => 'data:application/vnd.openxmlformats-officedocument.wordprocessingml.document;base64,' . base64_encode((string) $bytes),
        ])->assertCreated()
            ->assertJsonPath('mimeType', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->assertJsonPath('available', true);

        $document = DB::table('documents')->where('document_id', $response->json('id'))->first();
        $this->assertDatabaseHas('document_versions', [
            'document_id' => $document->document_id,
            'file_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function test_document_extension_must_match_file_contents(): void
    {
        $this->withHeader('X-CSRF-TOKEN', $this->csrfToken)->postJson('/api/documents', [
            'fileName' => 'report.pdf',
            'data' => 'data:text/plain;base64,' . base64_encode('not a PDF'),
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'The file contents do not match a supported document type.');

        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_public_document_route_serves_only_public_documents_without_admin_auth(): void
    {
        $this->app['auth']->guard('web')->logout();
        $bytes = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
        Storage::disk('local')->put('documents/public.pdf', $bytes);
        Storage::disk('local')->put('documents/private.pdf', $bytes);

        $publicId = DB::table('documents')->insertGetId([
            'category_id' => null,
            'created_by' => 1,
            'title' => 'Public file',
            'file_name' => 'public.pdf',
            'file_path' => 'private/documents/public.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => strlen($bytes),
            'access_level' => 'public',
        ]);
        $privateId = DB::table('documents')->insertGetId([
            'category_id' => null,
            'created_by' => 1,
            'title' => 'Private file',
            'file_name' => 'private.pdf',
            'file_path' => 'private/documents/private.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => strlen($bytes),
            'access_level' => 'private',
        ]);

        $this->get('/api/public/documents/' . $publicId)
            ->assertOk()
            ->assertDownload('public.pdf');
        $this->get('/api/public/documents/' . $privateId)->assertNotFound();
    }

    public function test_public_media_route_serves_public_images_without_admin_auth(): void
    {
        $this->app['auth']->guard('web')->logout();
        Storage::disk('public')->put('media/public.png', 'image bytes');
        $mediaId = DB::table('media')->insertGetId([
            'file_name' => 'public.png',
            'file_path' => '/storage/media/public.png',
            'file_type' => 'image/png',
            'mime_type' => 'image/png',
            'uploaded_by' => 1,
        ]);

        $this->get('/api/public/media/' . $mediaId)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }
}
