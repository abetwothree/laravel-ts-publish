<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Visibility;

final class InlineEnumCaseMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide a pure and a backed enum case, each written inline.
     *
     * @return array<string, mixed>
     */
    public function provide(Model $model): array
    {
        return [
            'visibility' => Visibility::Public,
            'status' => Status::Published,
        ];
    }
}
