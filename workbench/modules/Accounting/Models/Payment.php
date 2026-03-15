<?php

namespace Workbench\Accounting\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\Accounting\Enums\DueAtNotice;
use Workbench\Accounting\Enums\PaymentStatus;
use Workbench\App\Enums\Currency;
use Workbench\App\Enums\PaymentMethod;

class Payment extends Model
{
    protected $fillable = [
        'invoice_id',
        'status',
        'method',
        'currency',
        'amount',
        'reference',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'method' => PaymentMethod::class,
            'currency' => Currency::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    protected function dueNotice(): Attribute
    {
        return Attribute::get(
            function (): DueAtNotice {
                if ($this->paid_at->isFuture()) {
                    return DueAtNotice::ComingUp;
                }

                if ($this->paid_at->isToday()) {
                    return DueAtNotice::DueToday;
                }

                return DueAtNotice::PastDue;
            });
    }
}
