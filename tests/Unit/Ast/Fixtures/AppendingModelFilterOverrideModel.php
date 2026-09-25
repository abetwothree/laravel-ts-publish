<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A model whose only() override returns another model, which appends its own accessors. */
final class AppendingModelFilterOverrideModel extends Model
{
    protected $table = 'posts';

    /** @param  array<int, string>|string  $attributes */
    public function only($attributes): HiddenFilterOverrideModel
    {
        return new HiddenFilterOverrideModel;
    }

    /** @return HasMany<AppendingModelFilterOverrideModel, $this> */
    public function twins(): HasMany
    {
        return $this->hasMany(self::class, 'user_id');
    }
}
