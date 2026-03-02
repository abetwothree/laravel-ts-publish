<?php

namespace Workbench\App\Models;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'sort_order',
        'is_active',
        'metadata',
    ];

    #[TsCasts(['metadata' => 'Record<string, string | number>'])]
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /** Self-referencing: parent category */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Self-referencing: child categories */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** Posts in this category */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /** Full path breadcrumb (e.g. "Electronics > Phones > Smartphones") */
    protected function breadcrumb(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->parent
                ? $this->parent->breadcrumb.' > '.$this->name
                : $this->name,
        );
    }
}
