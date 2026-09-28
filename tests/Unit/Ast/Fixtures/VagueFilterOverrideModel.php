<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A model whose only() override declares `: array` and whose except() override declares `: mixed`, returns too vague
 * to publish, so both keep Model's filter answer.
 */
final class VagueFilterOverrideModel extends Model
{
    protected $table = 'posts';

    /** @param  array<int, string>|string  $attributes */
    public function only($attributes): array
    {
        return parent::only($attributes);
    }

    /** @param  array<int, string>|string  $attributes */
    public function except($attributes): mixed
    {
        return parent::except($attributes);
    }

    /** @return BelongsTo<VagueFilterOverrideModel, $this> */
    public function twin(): BelongsTo
    {
        return $this->belongsTo(self::class, 'user_id');
    }
}
