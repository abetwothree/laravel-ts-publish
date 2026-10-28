<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Status;
use Workbench\App\Packages\Ui\Panel;

final class OverriddenUnionListMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide metadata whose docblock lists a union of an overridden class and an enum the body imports.
     *
     * @return array{listed: list<Panel|Status>}
     */
    public function provide(Model $model): array
    {
        return ['listed' => [['kind' => 'node']], 'status' => Status::Published];
    }
}
