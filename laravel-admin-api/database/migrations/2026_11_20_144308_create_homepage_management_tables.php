<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('homepage_settings')) {
            Schema::create('homepage_settings', function (Blueprint $table) {
                $table->bigIncrements('setting_id');
                $table->string('setting_key', 100)->unique();
                $table->longText('setting_value')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('homepage_banners')) {
            Schema::create('homepage_banners', function (Blueprint $table) {
                $table->bigIncrements('banner_id');
                $table->string('title', 255);
                $table->text('subtitle')->nullable();
                $table->string('image_path', 500);
                $table->string('button_label', 100)->nullable();
                $table->string('button_url', 2048)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['is_active', 'sort_order']);
            });
        }

        if (! Schema::hasTable('homepage_featured_items')) {
            Schema::create('homepage_featured_items', function (Blueprint $table) {
                $table->bigIncrements('featured_item_id');
                $table->enum('content_type', ['news', 'announcements', 'events']);
                // The referenced content tables use INT UNSIGNED primary keys.
                $table->unsignedInteger('content_id');
                $table->string('section_title', 255)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['content_type', 'content_id'], 'homepage_featured_content_idx');
                $table->index(['is_active', 'sort_order'], 'homepage_featured_order_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_featured_items');
        Schema::dropIfExists('homepage_banners');
        Schema::dropIfExists('homepage_settings');
    }
};
