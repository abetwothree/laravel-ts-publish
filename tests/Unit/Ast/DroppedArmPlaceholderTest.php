<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\DroppedArmPlaceholderResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\IdiomPost;
use Workbench\App\Http\Resources\DeclaredPrecedenceResource;
use Workbench\App\Http\Resources\UnionHonestyResource;
use Workbench\App\Models\Post;

/**
 * The warnings of one kind a fresh analysis of one resource raises, each prefixed with the subject it names.
 *
 * @param  class-string  $class
 * @return list<string>
 */
function droppedArmWarnings(string $class, string $kind): array
{
    AnalysisWarnings::reset();
    resolve(AnalysisMemo::class)->reset();
    publishedResourceKeyTypes($class, Post::class);

    return array_values(array_map(
        fn (array $warning): string => $warning['subject'].': '.$warning['message'],
        array_filter(AnalysisWarnings::all(), fn (array $warning): bool => str_contains($warning['message'], $kind)),
    ));
}

/**
 * The placeholder warnings a fresh analysis of one resource raises.
 *
 * @param  class-string  $class
 * @return list<string>
 */
function placeholderWarnings(string $class): array
{
    return droppedArmWarnings($class, 'was left out of its union');
}

/**
 * The warnings a fresh analysis of one resource raises for a join that left out a value it cannot type.
 *
 * @param  class-string  $class
 * @return list<string>
 */
function joinDropWarnings(string $class): array
{
    return droppedArmWarnings($class, 'which could not be typed, so its published type may not admit');
}

/**
 * The run-output message for a value a dropped arm leaves as a placeholder.
 */
function placeholderWarning(int $line, string $published, string $expression): string
{
    return DroppedArmPlaceholderResource::class.": The value on line {$line} publishes {$published}, because `{$expression}` "
        .'could not be typed and was left out of its union. Give what it reads a return type or a `@return` docblock.';
}

test('a value left only never[] by a dropped arm warns with the arm it left out', function () {
    $never = '`never[]`, an array that is always empty';

    expect(placeholderWarnings(DroppedArmPlaceholderResource::class))
        ->toContain(placeholderWarning(33, $never, '$this->opaqueRows()'))
        ->toContain(placeholderWarning(34, $never, '$rows'))
        ->toContain(placeholderWarning(54, $never, '$this->opaqueRows()'))
        ->and(publishedResourceKeyTypes(DroppedArmPlaceholderResource::class, Post::class))->toMatchArray([
            'ternary' => 'never[]',
            'coalesce' => 'never[]',
            'literal' => 'never[]',
            'kept' => 'string | null',
            'walked?' => '{ id: number; rows: never[] }',
        ]);
});

test('a value left only null by a dropped arm warns too', function () {
    $null = '`null`, as if it were always null';

    expect(placeholderWarnings(DroppedArmPlaceholderResource::class))->toBe([
        placeholderWarning(33, '`never[]`, an array that is always empty', '$this->opaqueRows()'),
        placeholderWarning(34, '`never[]`, an array that is always empty', '$rows'),
        placeholderWarning(36, '`null | never[]`, as if it were always null or an empty array', '$this->opaqueRows()'),
        placeholderWarning(27, $null, '$this->opaqueRows()'),
        placeholderWarning(43, $null, '$this->opaqueRows()'),
        placeholderWarning(54, '`never[]`, an array that is always empty', '$this->opaqueRows()'),
    ])
        ->and(placeholderWarnings(UnionHonestyResource::class))->toBe(array_map(
            fn (int $line): string => UnionHonestyResource::class.": The value on line {$line} publishes {$null}, because "
                .'`$this->opaqueValue()` could not be typed and was left out of its union. Give what it reads a return type '
                .'or a `@return` docblock.',
            [28, 29, 31],
        ))
        ->and(publishedResourceKeyTypes(DroppedArmPlaceholderResource::class, Post::class))->toMatchArray([
            'both' => 'never[] | null',
            'unpublished_tag' => 'null',
            'parameter?' => 'null',
        ]);
});

test('an @var that replaces the placeholder withdraws the warning', function () {
    expect(placeholderWarnings(DeclaredPrecedenceResource::class))->toBe([])
        ->and(implode("\n", placeholderWarnings(DroppedArmPlaceholderResource::class)))->not->toContain('line 30 ')
        ->and(publishedResourceKeyTypes(DroppedArmPlaceholderResource::class, Post::class))
        ->toMatchArray(['declared_tag' => 'string[] | null']);
});

// The getter body's `null` gives way to what the model declares, so the body's placeholder is never published.
test('an accessor whose declaration replaces the placeholder raises no warning', function () {
    AnalysisWarnings::reset();

    expect(resolve(ModelAttributeResolver::class)->resolveAttribute(IdiomPost::class, 'nickname')['type'])->toBe('unknown')
        ->and(array_filter(
            array_column(AnalysisWarnings::all(), 'message'),
            fn (string $message): bool => str_contains($message, 'was left out of its union'),
        ))->toBe([]);
});

test('unknown[] beside never[] publishes unknown[]', function () {
    expect(publishedResourceKeyTypes(DroppedArmPlaceholderResource::class, Post::class))
        ->toMatchArray(['listed' => 'unknown[]'])
        ->and(implode("\n", placeholderWarnings(DroppedArmPlaceholderResource::class)))->not->toContain('line 39 ');
});

// Each value a join leaves out still reaches the read, so a type it keeps beside them may not admit all Laravel sends.
test('a join that leaves out a value it cannot type warns that the published type may not admit every value', function () {
    $warning = fn (int $line): string => DroppedArmPlaceholderResource::class.": The value on line {$line} leaves out "
        .'`$this->opaqueRows()`, which could not be typed, so its published type may not admit every value it sends. '
        .'Give what it reads a return type or a `@return` docblock.';

    expect(joinDropWarnings(DroppedArmPlaceholderResource::class))->toBe([$warning(61), $warning(72)])
        ->and(publishedResourceKeyTypes(DroppedArmPlaceholderResource::class, Post::class))->toMatchArray([
            'parameter_kept?' => 'string',
            'walked_kept?' => '{ id: number; title: string }',
        ])
        ->and(joinDropWarnings(UnionHonestyResource::class))->toHaveCount(3)
        ->and(implode("\n", joinDropWarnings(UnionHonestyResource::class)))->not->toContain('line 28 ');
});
