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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignIdFor(User::class)->constrained();
            $table->unsignedTinyInteger('status')->default(0);
            $table->string('payment_method', 50)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->jsonb('shipping_address')->nullable();
            $table->jsonb('billing_address')->nullable();
            $table->text('notes')->nullable();
            $table->dateTimeTz('placed_at')->nullable();
            $table->dateTimeTz('paid_at')->nullable();
            $table->dateTimeTz('shipped_at')->nullable();
            $table->dateTimeTz('delivered_at')->nullable();
            $table->dateTimeTz('cancelled_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
