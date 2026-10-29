<?php

declare(strict_types=1);

namespace Workbench\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\Shipping\Enums\Status;

class TrackingEvent extends Model
{
    protected $fillable = [
        'shipment_id',
        'status',
        'location',
        'description',
        'occurred_at',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'occurred_at' => 'datetime',
        ];
    }
}
