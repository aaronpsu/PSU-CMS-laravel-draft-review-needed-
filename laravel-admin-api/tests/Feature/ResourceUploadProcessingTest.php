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
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('uploaded_by')->nullable();
            $table->dateTime('created_at')->nullable();
        });
        Schema::create('document_categories', function (Blueprint $table): void {
            $table->increments('category_id');
            $table->string('name');
        });
        Schema::create('documents', function (Blueprint $table): void {
            $table->increments('document_id');
            $table->unsignedInteger('category_id');
            $table->unsignedInteger('created_by');
            $table->string('title')->nullable();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
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
        DB::table('document_categories')->insert(['category_id' => 1, 'name' => 'Reports']);

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
}
