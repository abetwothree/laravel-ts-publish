<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

final class EmptyValuesModelMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide empty containers whose TypeScript spelling depends on the declared type.
     *
     * @return array{
     *     flags: array<string, bool>,
     *     tags: list<string>,
     *     nested: array{items: array<string, int>, ids: list<int>},
     *     rows: list<array<string, int>>,
     *     opaque: array<string, mixed>,
     *     explicit: object,
     *     maybe: array<array-key, int>,
     * }
     */
    #[TsCasts([
        'opaque' => ['type' => 'OpaqueShape', 'import' => '@/types/opaque-shape'],
        'explicit' => 'Record<string, never>',
    ])]
    public function provide(Model $model): array
    {
        return [
            'flags' => [],
            'tags' => [],
            'nested' => ['items' => [], 'ids' => []],
            'rows' => [[], []],
            'opaque' => [],
            'explicit' => (object) [],
            // array<array-key, int> renders as `number[] | Record<string, number>`: neither list nor object, left as [].
            'maybe' => [],
        ];
    }
}
