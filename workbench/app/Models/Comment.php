<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    protected $fillable = [
        'content',
        'post_id',
        'user_id',
        'parent_id',
        'is_flagged',
        'flagged_at',
        'metadata',
    ];

    #[TsCasts(['metadata' => 'Record<string, unknown>'])]
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_flagged' => 'boolean',
            'flagged_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Short preview of the comment */
    protected function preview(): Attribute
    {
        return Attribute::make(
            get: fn (): string => substr($this->content ?? '', 0, 100),
        );
    }
}
