<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Http\Resources\UserResource;

/** A model on the `posts` table whose getter body leaves a key the `null` a dropped arm left, beside a resource. */
class PlaceholderAccessorPost extends Model
{
    protected $table = 'posts';

    /** A resource the model file cannot import, beside a value the engine cannot type or `null`. */
    protected function authorCard(): Attribute
    {
        return Attribute::get(fn () => [
            'author' => new UserResource($this->getAttribute('user_id')),
            'note' => $this->getKey() > 0 ? json_decode('"x"') : null,
        ]);
    }
}
