<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcels', function (Blueprint $table) {
            $table->id();
            $table->string('handler');
            $table->foreignId('handler_id');
            $table->foreignId('sender_id');
            $table->json('manifest');
            $table->foreignId('manifest_id');
            $table->unsignedTinyInteger('priority');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcels');
    }
};
