<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;

/**
 * Nullable JSON columns whose `@property` tags name a `@phpstan-type` alias whole, inside a list and inside a shape,
 * beside aliases that name another alias, themselves, each other or two classes.
 *
 * @phpstan-type Row = array{a: int}
 * @phpstan-type Rows = list<Row>
 * @phpstan-type Tree = list<Tree>
 * @phpstan-type Left = list<Right>
 * @phpstan-type Right = list<Left>
 * @phpstan-type Party = User|Team
 *
 * @property Row|null $owner_snapshot
 * @property list<Row>|null $tags
 * @property array{row: Row}|null $meta_info
 */
class TypeAliasShapeModel extends Model
{
    protected $table = 'property_docblock_fixtures';

    /**
     * Declare the alias-typed rows.
     *
     * @return list<Row>
     */
    public function rows(): array
    {
        return [['a' => 1]];
    }

    /**
     * Declare the party an alias union names beside one of its own classes.
     *
     * @return Party|User|null
     */
    public function party(): ?Model
    {
        return null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'meta_info' => 'array',
            'owner_snapshot' => 'array',
        ];
    }
}
