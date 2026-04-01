<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 50)->nullable()->after('email');
            $table->string('membership_level', 50)->nullable()->after('role');
            $table->string('phone', 20)->nullable()->after('password');
            $table->string('avatar')->nullable()->after('phone');
            $table->text('bio')->nullable()->after('avatar');
            $table->jsonb('settings')->nullable()->after('bio');
            $table->timestampTz('last_login_at')->nullable()->after('options');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role',
                'membership_level',
                'phone',
                'avatar',
                'bio',
                'settings',
                'last_login_at',
                'last_login_ip',
            ]);
        });
    }
};
