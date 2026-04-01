<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Workbench\App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->foreignIdFor(User::class);
            $table->boolean('status')->default(false);
            $table->dateTimeTz('published_at')->nullable();
            $table->binary('metadata')->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->enum('category', ['news', 'tutorial', 'opinion'])->default('news');
            $table->jsonb('options')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
