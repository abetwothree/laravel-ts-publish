<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /** Read at runtime, so a filter given this list names no key it could type. */
    protected $filterKeys = ['id'];

    /** Relation filters on a single relation and a to-many one, written in the model itself and read as a method body. */
    public function relationSummary(): array
    {
        return [
            'id' => $this->id,
            'author' => $this->user->only(['id', 'name']),
            'author_role' => $this->user?->only(['id', 'role']),
            'post_fields' => $this->post->except($this->filterKeys),
            'replies' => $this->replies->only([1, 2]),
            'kept_replies' => $this->replies?->except($this->filterKeys),
            'reply_previews' => $this->replies->map->only(['id', 'content']),
        ];
    }

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

    /** Self-referencing: replies to this comment */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** Short preview of the comment */
    protected function preview(): Attribute
    {
        return Attribute::make(
            get: fn (): string => substr($this->content ?? '', 0, 100),
        );
    }

    /** The same relation filters, read as a getter body. */
    protected function relationPicks(): Attribute
    {
        return Attribute::get(fn () => [
            'id' => $this->id,
            'author' => $this->user->only(['id', 'name']),
            'author_role' => $this->user?->only(['id', 'role']),
            'post_fields' => $this->post->except($this->filterKeys),
            'replies' => $this->replies->only([1, 2]),
            'kept_replies' => $this->replies?->except($this->filterKeys),
            'reply_previews' => $this->replies->map->only(['id', 'content']),
        ]);
    }
}
