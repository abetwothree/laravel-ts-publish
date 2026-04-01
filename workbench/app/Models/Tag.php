<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Tag extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'color',
    ];

    /** Posts with this tag (polymorphic many-to-many) */
    public function posts(): MorphToMany
    {
        return $this->morphedByMany(Post::class, 'taggable');
    }

    /** Products with this tag (polymorphic many-to-many) */
    public function products(): MorphToMany
    {
        return $this->morphedByMany(Product::class, 'taggable');
    }
}
