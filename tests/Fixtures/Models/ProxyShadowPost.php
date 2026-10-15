<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Models\Category;
use Workbench\App\Models\User;

/** A post whose old-style `author` accessor holds a Category, beside an `author()` relation to a User. */
final class ProxyShadowPost extends Model
{
    protected $table = 'posts';

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The accessor Model::__get() reads before the relation of the same name. */
    public function getAuthorAttribute(): Category
    {
        return new Category(['name' => 'Accessor Category']);
    }
}
