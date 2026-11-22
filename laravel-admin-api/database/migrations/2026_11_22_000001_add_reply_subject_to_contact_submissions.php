<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contact_submissions') && ! Schema::hasColumn('contact_submissions', 'reply_subject')) {
            Schema::table('contact_submissions', function (Blueprint $table): void {
                $table->string('reply_subject', 255)->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contact_submissions') && Schema::hasColumn('contact_submissions', 'reply_subject')) {
            Schema::table('contact_submissions', function (Blueprint $table): void {
                $table->dropColumn('reply_subject');
            });
        }
    }
};
