<?php

namespace Workbench\App\Models;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

#[TsCasts([
    'changes' => '{ attributes: Record<string, unknown>; old: Record<string, unknown> }',
])]
class TrackingEvent extends Model
{
    protected $fillable = [
        'shipment_id',
        'status',
        'location',
        'description',
        'occurred_at',
    ];

    public function changes(): Collection
    {
        return collect([
            'attributes' => [
                'status' => $this->status,
                'location' => $this->location,
            ],
            'old' => [],
        ])->only(['attributes', 'old']);
    }

    public function getChangesAttribute(): Collection
    {
        return $this->changes();
    }
}
