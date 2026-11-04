<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Role;

/** A provider whose docblock shape names an enum nested in a shape and at its top level. */
final class NestedEnumMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide metadata pairing a role with its kind.
     *
     * @return array{pair: array{kind: 'Role', r: Role}, top: Role}
     */
    public function provide(Model $model): array
    {
        return ['pair' => ['kind' => 'Role', 'r' => Role::Admin], 'top' => Role::Admin];
    }
}
