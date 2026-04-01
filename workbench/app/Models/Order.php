<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Workbench\App\Enums\Currency;
use Workbench\App\Enums\OrderStatus;
use Workbench\App\Enums\PaymentMethod;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'ulid',
        'user_id',
        'status',
        'payment_method',
        'currency',
        'subtotal',
        'tax',
        'discount',
        'total',
        'shipping_address',
        'billing_address',
        'notes',
        'placed_at',
        'paid_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'ip_address',
        'user_agent',
    ];

    #[TsCasts([
        'shipping_address' => '{ line_1: string; line_2?: string; city: string; state?: string; postal_code: string; country_code: string }',
        'billing_address' => '{ line_1: string; line_2?: string; city: string; state?: string; postal_code: string; country_code: string }',
    ])]
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'currency' => Currency::class,
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'shipping_address' => 'array',
            'billing_address' => 'array',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Number of line items in this order */
    protected function itemCount(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->items()->count(),
        );
    }

    /** Whether the order has been paid */
    protected function isPaid(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->paid_at !== null,
        );
    }

    /** Formatted total with currency symbol */
    protected function formattedTotal(): Attribute
    {
        return Attribute::make(
            get: fn (): string => match ($this->currency) {
                Currency::Usd => '$',
                Currency::Eur => '€',
                Currency::Gbp => '£',
                Currency::Jpy => '¥',
                Currency::Cad => 'C$',
                default => '$',
            }.number_format((float) $this->total, 2),
        );
    }

    /** Trimmed notes — accessor on a nullable DB column */
    protected function notes(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): string => trim($value ?? ''),
        );
    }

    /** Write-only mutator (no getter) for a non-DB column */
    protected function searchIndex(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => strtolower($value),
        );
    }
}
