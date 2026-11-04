<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Models\User;

/** A provider whose docblock shape names a model nested in a shape. */
final class NestedModelMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide metadata pairing a user with its kind.
     *
     * @return array{pair: array{kind: 'User', r: User}}
     */
    public function provide(Model $model): array
    {
        return ['pair' => ['kind' => 'User', 'r' => new User(['name' => 'Ada'])]];
    }
}
