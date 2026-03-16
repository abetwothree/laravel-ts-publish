<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strict_task_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('category_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strict_task_assignments');
    }
};
