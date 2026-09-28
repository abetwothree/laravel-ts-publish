<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

final class TemplateLiteralModelMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide an empty `b` beside a template-literal `a` holding a quoted backtick or an escaped quote.
     *
     * @return array<string, array<string, mixed>>
     */
    #[TsCasts([
        'singleQuoted' => "{ a: `\${'`'}`; b: Record<string, number> }",
        'doubleQuoted' => '{ a: `${"`"}`; b: Record<string, number> }',
        'escapedDoublePair' => '{ a: `\"${string}\"`; b: Record<string, number> }',
        'escapedDoubleText' => '{ a: `say \"hi\"`; b: Record<string, number> }',
        'escapedSinglePair' => '{ a: `\\\'${string}\\\'`; b: Record<string, number> }',
        'apostropheThenEscaped' => '{ a: `a\'\\\'`; b: Record<string, number> }',
    ])]
    public function provide(Model $model): array
    {
        return [
            'singleQuoted' => ['a' => '`', 'b' => []],
            'doubleQuoted' => ['a' => '`', 'b' => []],
            'escapedDoublePair' => ['a' => '"x"', 'b' => []],
            'escapedDoubleText' => ['a' => 'say "hi"', 'b' => []],
            'escapedSinglePair' => ['a' => "'x'", 'b' => []],
            'apostropheThenEscaped' => ['a' => "a''", 'b' => []],
        ];
    }
}
