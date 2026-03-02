<?php

namespace Workbench\App\Models;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    protected $fillable = [
        'user_id',
        'bio',
        'avatar_url',
        'date_of_birth',
        'website',
        'phone_number',
        'social_links',
        'settings',
        'timezone',
        'locale',
    ];

    #[TsCasts([
        'social_links' => '{ twitter?: string; github?: string; linkedin?: string; website?: string }',
        'settings' => '{ notifications_enabled: boolean; theme: "light" | "dark"; language: string }',
    ])]
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'social_links' => 'array',
            'settings' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Computed age from date_of_birth */
    protected function age(): Attribute
    {
        return Attribute::make(
            get: fn (): ?int => $this->date_of_birth?->age,
        );
    }

    /** Full display name combining user name and bio snippet */
    protected function displaySummary(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->user->name.($this->bio ? ' — '.substr($this->bio, 0, 50) : ''),
        );
    }
}
