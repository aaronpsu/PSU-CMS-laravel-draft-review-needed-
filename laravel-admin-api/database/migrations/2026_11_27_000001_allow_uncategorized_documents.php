<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE documents MODIFY category_id INT(11) NULL DEFAULT NULL');
    }

    public function down(): void
    {
        if (DB::table('documents')->whereNull('category_id')->exists()) {
            $categoryId = DB::table('document_categories')->orderBy('category_id')->value('category_id');
            if ($categoryId === null) {
                throw new RuntimeException('Cannot restore the required document category while uncategorized documents exist and no categories are available.');
            }
            DB::table('documents')->whereNull('category_id')->update(['category_id' => $categoryId]);
        }

        DB::statement('ALTER TABLE documents MODIFY category_id INT(11) NOT NULL');
    }
};
