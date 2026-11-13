<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Workbench\App\Models\Tag;
use Workbench\App\Models\User;

/**
 * A model whose only() override returns a list of models and whose except() override returns a shape holding a model,
 * so the first names a model inside a list and the second one nested in a key, and again one shape deeper.
 */
final class ListTypedFilterOverrideModel extends Model
{
    protected $table = 'tags';

    /**
     * @param  array<int, string>|string  $attributes
     * @return array<int, Tag>
     */
    public function only($attributes): array
    {
        return [];
    }

    /**
     * @param  array<int, string>|string  $attributes
     * @return array{owner: User, meta: array{author: User, n: int}, id: int}
     */
    public function except($attributes): array
    {
        return ['owner' => new User, 'meta' => ['author' => new User, 'n' => 1], 'id' => 0];
    }
}
