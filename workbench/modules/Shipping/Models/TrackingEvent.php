<?php

namespace Workbench\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\Shipping\Enums\ShipmentStatus;

class TrackingEvent extends Model
{
    protected $fillable = [
        'shipment_id',
        'status',
        'location',
        'description',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
