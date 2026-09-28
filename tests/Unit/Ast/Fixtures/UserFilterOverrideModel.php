<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Workbench\App\Models\User;

/** A model whose only() override returns another model, whose columns, hidden ones and enum casts are its own. */
final class UserFilterOverrideModel extends Model
{
    protected $table = 'posts';

    /** @param  array<int, string>|string  $attributes */
    public function only($attributes): User
    {
        return new User;
    }

    /** @return HasMany<UserFilterOverrideModel, $this> */
    public function twins(): HasMany
    {
        return $this->hasMany(self::class, 'user_id');
    }
}
