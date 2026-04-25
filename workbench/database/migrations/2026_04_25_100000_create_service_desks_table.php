<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_desks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_agent_id')->nullable()->constrained('crm_users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_desks');
    }
};
