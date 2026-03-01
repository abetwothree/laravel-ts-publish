<?php

namespace Workbench\App\Models;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Enums\Status;

class Post extends Model
{
    #[TsCasts(['metadata' => 'Record<string, {title: string, content: string}>'])]
    public $casts = [
        'options' => 'array',
        'metadata' => 'array',
        'status' => Status::class,
    ];

    protected function titleDisplay(): Attribute
    {
        return Attribute::make(
            get: fn ($value): ?string => strtoupper($value),
            set: fn ($value): string => strtolower($value),
        );
    }

    public function author(): ?BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
