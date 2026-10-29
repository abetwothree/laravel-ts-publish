<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Status;
use Workbench\App\Packages\Ui\Panel;

final class OverriddenUnionMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide metadata whose docblock unions an overridden class with an enum the body imports.
     *
     * @return array{either: Panel|Status}
     */
    public function provide(Model $model): array
    {
        return ['either' => ['kind' => 'node'], 'status' => Status::Published];
    }
}
