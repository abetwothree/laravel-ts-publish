<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('composite_comments', function (Blueprint $table) {
            $table->id();
            $table->string('body');
            $table->string('commentable_type');
            $table->unsignedBigInteger('commentable_id_1');
            $table->unsignedBigInteger('commentable_id_2')->nullable();
            $table->timestamps();
        });

        Schema::create('strict_composite_comments', function (Blueprint $table) {
            $table->id();
            $table->string('body');
            $table->string('commentable_type');
            $table->unsignedBigInteger('commentable_id_1');
            $table->unsignedBigInteger('commentable_id_2');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('composite_comments');
        Schema::dropIfExists('strict_composite_comments');
    }
};
