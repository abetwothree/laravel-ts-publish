<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

final class TemplateLiteralModelMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide an empty `b` beside a template-literal `a` whose placeholder holds a quoted backtick.
     *
     * @return array<string, array<string, mixed>>
     */
    #[TsCasts([
        'singleQuoted' => "{ a: `\${'`'}`; b: Record<string, number> }",
        'doubleQuoted' => '{ a: `${"`"}`; b: Record<string, number> }',
    ])]
    public function provide(Model $model): array
    {
        return [
            'singleQuoted' => ['a' => '`', 'b' => []],
            'doubleQuoted' => ['a' => '`', 'b' => []],
        ];
    }
}
