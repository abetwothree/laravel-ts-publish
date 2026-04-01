<?php

declare(strict_types=1);

namespace Workbench\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Workbench\App\Models\Order;
use Workbench\Shipping\Enums\Carrier;
use Workbench\Shipping\Enums\Status;

class Shipment extends Model
{
    protected $fillable = [
        'order_id',
        'tracking_number',
        'carrier',
        'status',
        'weight_grams',
        'estimated_delivery_at',
        'shipped_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'carrier' => Carrier::class,
            'status' => Status::class,
            'weight_grams' => 'integer',
            'estimated_delivery_at' => 'date',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(TrackingEvent::class);
    }
}
