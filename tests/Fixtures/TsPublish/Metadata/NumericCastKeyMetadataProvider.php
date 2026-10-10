<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

final class NumericCastKeyMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide a payload whose cast names the numeric key `42`, which PHP stores as an int.
     *
     * @return array<array-key, mixed>
     */
    #[TsCasts(['label' => 'string', '42' => 'boolean'])]
    public function provide(Model $model): array
    {
        return ['label' => $model->getTable(), 42 => 1];
    }
}
