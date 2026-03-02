<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('user_id')->constrained('categories')->nullOnDelete();
            $table->string('visibility', 50)->nullable()->after('status');
            $table->unsignedTinyInteger('priority')->nullable()->after('visibility');
            $table->unsignedInteger('word_count')->nullable()->after('rating');
            $table->float('reading_time_minutes')->nullable()->after('word_count');
            $table->string('featured_image_url')->nullable()->after('reading_time_minutes');
            $table->boolean('is_pinned')->default(false)->after('featured_image_url');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn([
                'category_id',
                'visibility',
                'priority',
                'word_count',
                'reading_time_minutes',
                'featured_image_url',
                'is_pinned',
            ]);
        });
    }
};
