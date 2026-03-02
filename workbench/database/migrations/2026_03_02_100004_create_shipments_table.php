<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('tracking_number')->nullable()->unique();
            $table->string('carrier');
            $table->string('status')->default('pending');
            $table->unsignedInteger('weight_grams')->nullable();
            $table->date('estimated_delivery_at')->nullable();
            $table->datetime('shipped_at')->nullable();
            $table->datetime('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
