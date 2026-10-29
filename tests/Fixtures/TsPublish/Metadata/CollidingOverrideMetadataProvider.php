<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Person;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Models\User;

final class CollidingOverrideMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide metadata whose docblock spells a model like the override another key imports.
     *
     * @return array{person: Person, either: Person|User}
     */
    public function provide(Model $model): array
    {
        return ['person' => ['name' => 'Ada'], 'either' => ['name' => 'Ada']];
    }
}
