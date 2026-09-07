<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

final class AstEmptyValuesModelMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide empty containers with no return shape, so every type comes from body inference.
     *
     * @return array<string, mixed>
     */
    public function provide(Model $model): array
    {
        return [
            'empty' => [],
            'nested' => ['items' => []],
            'flags' => $this->flags(),
        ];
    }

    /**
     * A docblock-typed map so inference sees Record<string, boolean> for an empty value.
     *
     * @return array<string, bool>
     */
    private function flags(): array
    {
        return [];
    }
}
