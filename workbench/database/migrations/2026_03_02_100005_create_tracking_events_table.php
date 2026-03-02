<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->datetime('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_events');
    }
};
