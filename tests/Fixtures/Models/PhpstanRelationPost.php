<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Workbench\App\Models\Comment;

/** A post whose comments() relation, built through a helper, names its class only in `@phpstan-return`. */
class PhpstanRelationPost extends Model
{
    protected $table = 'posts';

    /**
     * @return mixed
     *
     * @phpstan-return HasMany<Comment, $this>
     */
    public function comments()
    {
        return $this->commentsOf(Comment::class);
    }

    /**
     * @param  class-string<Comment>  $related
     * @return HasMany<Comment, $this>
     */
    protected function commentsOf(string $related): HasMany
    {
        return $this->hasMany($related, 'post_id');
    }
}
