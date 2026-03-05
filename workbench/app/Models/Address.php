<?php

namespace Workbench\App\Models;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[TsCasts([
    'latitude' => 'number | null',
    'longitude' => 'number | null',
    'full_address' => 'string | null',
])]
class Address extends Model
{
    protected $fillable = [
        'user_id',
        'label',
        'line_1',
        'line_2',
        'city',
        'state',
        'postal_code',
        'country_code',
        'latitude',
        'longitude',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Full single-line address string */
    protected function fullAddress(): Attribute
    {
        return Attribute::make(
            get: fn (): string => collect([
                $this->line_1,
                $this->line_2,
                $this->city,
                $this->state,
                $this->postal_code,
                $this->country_code,
            ])->filter()->implode(', '),
        );
    }

    /** Whether coordinates are available */
    protected function hasCoordinates(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->latitude !== null && $this->longitude !== null,
        );
    }
}
