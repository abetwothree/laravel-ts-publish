<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\DroppedArmPlaceholderResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\DroppedBranchJoinResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\IdiomPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\PlaceholderAccessorPost;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use PhpParser\Node\Expr\Variable;
use Workbench\App\Http\Resources\DeclaredPrecedenceResource;
use Workbench\App\Http\Resources\UnionHonestyResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\Tag;

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
 * The warnings a placeholder raises inside one replaceable() read, run outside any other hold, that reach the run.
 *
 * @return list<string>
 */
function warningsKeptBy(bool $kept): array
{
    AnalysisWarnings::reset();
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));

    DroppedUnionArms::replaceable(
        fn (): array => DroppedUnionArms::warnOfPlaceholder(['type' => 'null', 'optional' => false], [new Variable('held')], $scope),
        fn (): bool => $kept,
    );

    return array_column(AnalysisWarnings::all(), 'message');
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
        ->and(joinDropWarnings(UnionHonestyResource::class))->toHaveCount(4)
        ->and(implode("\n", joinDropWarnings(UnionHonestyResource::class)))->not->toContain('line 28 ');
});

// A merge call's sides and a spread helper's returns join as a local's branches do, and leave a value out alike.
test('a merge call and a spread helper that leave out a value they cannot type warn as a join does', function () {
    $warning = fn (int $line): string => DroppedBranchJoinResource::class.": The value on line {$line} leaves out "
        .'`$this->opaqueRows()`, which could not be typed, so its published type may not admit every value it sends. '
        .'Give what it reads a return type or a `@return` docblock.';

    expect(joinDropWarnings(DroppedBranchJoinResource::class))->toBe([$warning(24), $warning(27), $warning(41), $warning(55)])
        ->and(placeholderWarnings(DroppedBranchJoinResource::class))->toBe([
            DroppedBranchJoinResource::class.': The value on line 31 publishes `null`, as if it were always null, because '
            .'`$this->opaqueRows()` could not be typed and was left out of its union. Give what it reads a return type or a '
            .'`@return` docblock.',
        ])
        ->and(publishedResourceKeyTypes(DroppedBranchJoinResource::class, Post::class))->toBe([
            'merged' => 'string',
            'merged_built' => 'string',
            'merged_null' => 'null',
            'spread' => 'string',
            'spread_built' => 'string',
        ]);
});

test('sends the value a merge call or a spread helper leaves out of its published type', function () {
    $tag = new Tag;
    $tag->forceFill(['id' => 9, 'name' => 'php']);
    $post = new Post;
    $post->forceFill(['id' => 2, 'title' => 'Banana']);
    $post->setRelation('tags', new EloquentCollection([$tag]));
    $sent = json_decode((string) json_encode(new DroppedBranchJoinResource($post)->resolve(Request::create('/'))), true);

    expect($sent)->toBeArray()
        ->and(array_map(fn (mixed $value): string => get_debug_type($value), $sent))->toBe([
            'merged' => 'array',
            'merged_built' => 'array',
            'merged_null' => 'array',
            'spread' => 'array',
            'spread_built' => 'string',
        ]);
});

// The resource's token has no import in the model file, so the getter publishes what the model declares instead.
test('an accessor body declined for a class its model file cannot name raises no warning', function () {
    AnalysisWarnings::reset();

    expect(resolve(ModelAttributeResolver::class)->resolveAttribute(PlaceholderAccessorPost::class, 'author_card')['type'])
        ->toBe('unknown')
        ->and(AnalysisWarnings::all())->toBe([]);
});

test('a replaceable read prints the warnings it holds only where they are kept', function () {
    expect(warningsKeptBy(true))->toHaveCount(1)
        ->and(warningsKeptBy(false))->toBe([]);
});

test('a replaceable read that throws leaves no hold behind', function () {
    AnalysisWarnings::reset();
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));

    expect(fn () => DroppedUnionArms::replaceable(fn () => throw new RuntimeException('read failed'), fn (): bool => true))
        ->toThrow(RuntimeException::class, 'read failed');

    DroppedUnionArms::warnOfPlaceholder(['type' => 'null', 'optional' => false], [new Variable('after')], $scope);

    expect(AnalysisWarnings::all())->toHaveCount(1);
});

test('a nested read passes a kept warning to the read around it, which decides whether it prints', function () {
    AnalysisWarnings::reset();
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));
    $inner = fn (bool $kept): Closure => fn () => DroppedUnionArms::replaceable(
        fn (): array => DroppedUnionArms::warnOfPlaceholder(['type' => 'null', 'optional' => false], [new Variable('inner')], $scope),
        fn (): bool => $kept,
    );

    DroppedUnionArms::replaceable($inner(true), fn (): bool => false);
    DroppedUnionArms::replaceable($inner(false), fn (): bool => true);

    expect(AnalysisWarnings::all())->toBe([]);

    DroppedUnionArms::replaceable($inner(true), fn (): bool => true);

    expect(array_column(AnalysisWarnings::all(), 'message'))->toHaveCount(1)
        ->each->toContain('`$inner`');
});
