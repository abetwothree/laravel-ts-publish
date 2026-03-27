<?php

namespace Workbench\Accounting\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Workbench\Accounting\Enums\InvoiceStatus;
use Workbench\App\Models\User;

class Invoice extends Model
{
    protected $fillable = [
        'user_id',
        'number',
        'status',
        'subtotal',
        'tax',
        'total',
        'due_at',
        'issued_at',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'due_at' => 'date',
            'issued_at' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    protected function latestPayment(): Attribute
    {
        return Attribute::make(
            get: fn (): ?Payment => $this->payments()->latest()->first(),
        );
    }
}
