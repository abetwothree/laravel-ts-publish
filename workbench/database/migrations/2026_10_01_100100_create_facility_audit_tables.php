<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->nullableMorphs('inspector');
            $table->timestamps();
        });

        Schema::create('audit_inspectors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('audit_trails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id');
            $table->string('action');
            $table->timestamps();
        });

        Schema::create('audit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_trail_id');
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_notes');
        Schema::dropIfExists('audit_trails');
        Schema::dropIfExists('audit_inspectors');
        Schema::dropIfExists('facilities');
    }
};
