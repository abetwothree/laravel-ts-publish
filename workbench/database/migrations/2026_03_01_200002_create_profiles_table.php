<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Workbench\App\Models\User;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->text('bio')->nullable();
            $table->string('avatar_url')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('website')->nullable();
            $table->string('phone_number')->nullable();
            $table->jsonb('social_links')->nullable();
            $table->jsonb('settings')->nullable();
            $table->jsonb('menu_settings')->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->string('locale', 10)->default('en');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
