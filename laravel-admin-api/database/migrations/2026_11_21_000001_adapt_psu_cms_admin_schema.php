<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'full_name')) {
                $table->string('full_name', 150)->nullable()->after('last_name');
            }
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 50)->nullable()->after('role_id');
            }
        });
        DB::statement("UPDATE users u LEFT JOIN roles r ON r.role_id = u.role_id SET u.full_name = TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), u.role = CASE LOWER(COALESCE(r.name, '')) WHEN 'super administrator' THEN 'admin' WHEN 'administrator' THEN 'admin' WHEN 'editor' THEN 'editor' ELSE 'author' END");

        foreach ([
            'campuses' => [['address', 'string', 255, true], ['status', 'string', 20, false]],
            'colleges' => [['slug', 'string', 200, true], ['description', 'text', null, true], ['email', 'string', 255, true], ['status', 'string', 20, false]],
            'departments' => [['slug', 'string', 200, true], ['status', 'string', 20, false]],
            'programs' => [['slug', 'string', 200, true]],
        ] as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns, $tableName): void {
                foreach ($columns as [$name, $type, $length, $nullable]) {
                    if (Schema::hasColumn($tableName, $name)) continue;
                    if ($type === 'text') $table->text($name)->nullable();
                    elseif ($type === 'string') $table->string($name, $length)->nullable($nullable);
                }
            });
        }
        DB::statement("UPDATE campuses SET status = 'active' WHERE status IS NULL");
        if (! DB::table('campuses')->exists()) {
            DB::table('campuses')->insert([
                'code' => 'MAIN',
                'name' => 'Main Campus',
                'is_main' => 1,
                'address' => null,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::statement("UPDATE colleges SET slug = LOWER(REPLACE(TRIM(name), ' ', '-')), status = 'active' WHERE slug IS NULL OR status IS NULL");
        DB::statement("UPDATE departments SET slug = LOWER(REPLACE(TRIM(name), ' ', '-')), status = 'active' WHERE slug IS NULL OR status IS NULL");
        DB::statement("UPDATE programs SET slug = LOWER(REPLACE(TRIM(name), ' ', '-')) WHERE slug IS NULL");

        foreach (['news_categories', 'announcement_categories'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'slug')) {
                    $table->string('slug', 150)->nullable();
                }
                if (! Schema::hasColumn($tableName, 'description')) {
                    $table->text('description')->nullable();
                }
            });
            DB::statement("UPDATE {$tableName} SET slug = LOWER(REPLACE(TRIM(name), ' ', '-')) WHERE slug IS NULL");
        }

        Schema::table('media', function (Blueprint $table): void {
            if (! Schema::hasColumn('media', 'mime_type')) $table->string('mime_type', 100)->nullable()->after('file_type');
            if (! Schema::hasColumn('media', 'file_size')) $table->unsignedBigInteger('file_size')->nullable()->after('file_size_kb');
        });
        DB::statement("UPDATE media SET mime_type = COALESCE(mime_type, file_type), file_size = COALESCE(file_size, file_size_kb * 1024)");

        Schema::table('documents', function (Blueprint $table): void {
            if (! Schema::hasColumn('documents', 'file_name')) $table->string('file_name', 255)->nullable()->after('title');
            if (! Schema::hasColumn('documents', 'file_path')) $table->string('file_path', 500)->nullable()->after('file_name');
            if (! Schema::hasColumn('documents', 'mime_type')) $table->string('mime_type', 100)->nullable()->after('file_path');
            if (! Schema::hasColumn('documents', 'file_size')) $table->unsignedBigInteger('file_size')->nullable()->after('mime_type');
        });
        DB::statement("UPDATE documents d JOIN document_versions v ON v.document_id = d.document_id SET d.file_name = COALESCE(d.file_name, v.file_name), d.file_path = COALESCE(d.file_path, v.file_path), d.mime_type = COALESCE(d.mime_type, v.file_type), d.file_size = COALESCE(d.file_size, v.file_size_kb * 1024)");

        Schema::table('announcements', function (Blueprint $table): void {
            if (! Schema::hasColumn('announcements', 'category_id')) {
                $table->unsignedInteger('category_id')->nullable()->after('title');
            }
            if (! Schema::hasColumn('announcements', 'attachment_document_id')) {
                $table->unsignedInteger('attachment_document_id')->nullable()->after('expires_at');
            }
            if (! Schema::hasColumn('announcements', 'author_name')) {
                $table->string('author_name', 150)->nullable()->after('title');
            }
        });
        if (Schema::hasTable('announcement_category_map')) {
            DB::statement("UPDATE announcements a JOIN announcement_category_map acm ON acm.announcement_id = a.announcement_id SET a.category_id = acm.category_id WHERE a.category_id IS NULL");
        }
        DB::statement("UPDATE announcements a LEFT JOIN content_attachments ca ON ca.announcement_id = a.announcement_id SET a.attachment_document_id = ca.document_id");

        Schema::table('event_categories', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_categories', 'id')) {
                $table->unsignedInteger('id')->nullable()->after('category_id');
            }
        });
        if (Schema::hasColumn('event_categories', 'category_id')) {
            DB::statement("UPDATE event_categories SET id = category_id WHERE id IS NULL");
        }
    }

    public function down(): void
    {
        // Compatibility columns are retained; the new schema remains the source of truth.
    }
};
