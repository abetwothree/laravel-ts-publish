<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('labelables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_id');
            $table->morphs('labelable');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('labelables');
        Schema::dropIfExists('labels');
    }
};
