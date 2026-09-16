<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Model;

/** A model whose only() override declares its return only as a docblock `array<string, mixed>`, too vague to publish. */
final class DocblockFilterOverrideModel extends Model
{
    protected $table = 'posts';

    /**
     * @param  array<int, string>|string  $attributes
     * @return array<string, mixed>
     */
    public function only($attributes)
    {
        return parent::only($attributes);
    }
}
