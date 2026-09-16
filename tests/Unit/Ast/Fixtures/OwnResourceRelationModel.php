<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Models\Category;
use Workbench\App\Models\Post;

/**
 * A model whose own `resource` relation leads to a Post, whose author is a User, while its own `author` leads to a
 * Category, so a filter read through the wrong one names the wrong model.
 */
final class OwnResourceRelationModel extends Model
{
    protected $table = 'posts';

    /** The Post author's filtered fields, read as a method body. */
    public function resourceAuthorFields(): array
    {
        return ['author' => $this->resource->author->only(['id', 'email'])];
    }

    /** @return BelongsTo<Post, $this> */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'id');
    }

    /** @return BelongsTo<Category, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /** The Post author's filtered fields, read as a getter body. */
    protected function resourceAuthor(): Attribute
    {
        return Attribute::get(fn () => $this->resource->author->only(['id', 'name']));
    }
}
