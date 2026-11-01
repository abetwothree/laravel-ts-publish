<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Builders\PublishedPostBuilder;
use Illuminate\Database\Eloquent\Builder;
use Workbench\App\Models\Post;

/** A test-only post on the `posts` table whose constructor picks its custom builder, which no declaration names. */
class ConstructedBuilderPost extends Post
{
    // Redeclared so the constructor's write stays on this class rather than every model's.
    protected static string $builder = Builder::class;

    protected $table = 'posts';

    /**
     * Pick the custom builder, then build the model.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        static::$builder = PublishedPostBuilder::class;

        parent::__construct($attributes);
    }
}
