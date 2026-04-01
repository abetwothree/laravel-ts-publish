<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Visibility;

class Post extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'content',
        'user_id',
        'category_id',
        'status',
        'visibility',
        'priority',
        'published_at',
        'metadata',
        'options',
        'rating',
        'category',
        'word_count',
        'reading_time_minutes',
        'featured_image_url',
        'is_pinned',
    ];

    #[TsCasts(['metadata' => 'Record<string, {title: string, content: string}>'])]
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'metadata' => 'array',
            'status' => Status::class,
            'visibility' => Visibility::class,
            'priority' => Priority::class,
            'published_at' => 'datetime',
            'rating' => 'decimal:2',
            'word_count' => 'integer',
            'reading_time_minutes' => 'float',
            'is_pinned' => 'boolean',
        ];
    }

    /** Title displayed in uppercase */
    protected function titleDisplay(): Attribute
    {
        return Attribute::make(
            get: fn ($value): ?string => strtoupper($value),
            set: fn ($value): string => strtolower($value),
        );
    }

    /** Excerpt of the post content */
    protected function excerpt(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->content ? substr(strip_tags($this->content), 0, 200).'...' : null,
        );
    }

    /** Estimated reading time formatted */
    protected function readingTime(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->reading_time_minutes
                ? round($this->reading_time_minutes).' min read'
                : 'Quick read',
        );
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function categoryRel(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** Polymorphic many-to-many with tags */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /** Polymorphic images */
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }
}
