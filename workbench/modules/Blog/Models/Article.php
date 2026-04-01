<?php

declare(strict_types=1);

namespace Workbench\Blog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Workbench\App\Models\User;
use Workbench\Blog\Enums\ArticleStatus;
use Workbench\Blog\Enums\ContentType;

class Article extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'excerpt',
        'body',
        'status',
        'content_type',
        'featured_image',
        'meta_description',
        'is_featured',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ArticleStatus::class,
            'content_type' => ContentType::class,
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }
}
