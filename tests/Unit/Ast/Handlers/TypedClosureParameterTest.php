<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\DeclaredClosureResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\TypedClosureParameterResource;
use PhpParser\Node\Stmt\Expression;
use Workbench\App\Models\Post;

it('binds a closure parameter to the class its native type names', function () {
    expect(publishedResourceKeyTypes(TypedClosureParameterResource::class, Post::class))->toBe([
        'codes' => '{ id: string }[]',
        'opaque_codes' => '{ id: string }[]',
        'spread_comments' => "(Omit<Comment, 'flag'> & { flag: boolean })[]",
        'comment_authors' => '(string | null)[]',
        'options' => '{ id: number; d: number }[]',
        'nullable_options' => '{ id: number }[]',
        'replaced_options' => '{ id: string }[]',
        'null_default?' => 'null',
        'null_default_member?' => 'unknown',
        'enum_default?' => 'StatusType',
        'unit_enum_default?' => 'StatusKind',
    ]);
});

// A writer's value binding that names a class, or is only null, says more than the class the parameter declares.
it('binds no declared class over a default that names an enum case or is null', function () {
    $sent = new TypedClosureParameterResource(new Post)->resolve(request());

    expect(publishedResourceKeyTypes(TypedClosureParameterResource::class, Post::class))->toMatchArray([
        'null_default?' => 'null',
        'null_default_member?' => 'unknown',
        'enum_default?' => 'StatusType',
        'unit_enum_default?' => 'StatusKind',
    ])->and($sent)->toMatchArray([
        'null_default' => null,
        'null_default_member' => null,
        'enum_default' => 1,
        'unit_enum_default' => 'Published',
    ]);
});

// No writer binds this closure's parameter, so only its declared class can, before the body's write ends it.
it('ends a typed parameter\'s binding at a write that replaces it with another class', function () {
    $statement = new AstParser()->parseSource(<<<'PHP'
        <?php
        function (\AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\MapOption $option) {
            $kept = $option->itemId;
            $option = new \AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\DockCode('a');

            return ['kept' => $kept, 'code' => $option->code];
        };
        PHP)[0];
    $scope = new AnalysisScope(new ReflectionClass(TypedClosureParameterResource::class), Post::class);
    $engine = new ResourceAstAnalyzer(new ReflectionClass(TypedClosureParameterResource::class), Post::class, 'toArray', null, $scope);

    expect($statement)->toBeInstanceOf(Expression::class)
        ->and($engine->resolve($statement->expr)['type'])->toBe('{ kept: number; code: string }');
});

/**
 * What Laravel sends for DeclaredClosureResource over a post titled `Hello`, decoded from its JSON.
 *
 * @return array<string, mixed>
 */
function declaredClosureSends(): array
{
    /** @var array<string, mixed> */
    return json_decode((string) json_encode(new DeclaredClosureResource(new Post()->forceFill(['id' => 1, 'title' => 'Hello']))->resolve(request())), true);
}

// A value closure's `@param` names its class as a native type would, and `X|null` reads as `?X` does. A collected
// value nothing types may hold string keys, which all() keeps.
it('binds a value closure parameter by its @param class, and by the class either nullable spelling names', function () {
    expect(publishedResourceKeyTypes(DeclaredClosureResource::class, Post::class))->toMatchArray([
        'native_param_titles' => 'string[] | Record<string, string>',
        'doc_param_titles' => 'string[] | Record<string, string>',
        'doc_param_item_ids' => 'number[] | Record<string, number>',
        'nullable_titles' => '(string | null)[] | Record<string, string | null>',
        'or_null_titles' => '(string | null)[] | Record<string, string | null>',
    ])->and(declaredClosureSends())->toMatchArray([
        'native_param_titles' => ['Hello'],
        'doc_param_titles' => ['Hello'],
        'doc_param_item_ids' => [7],
        'nullable_titles' => ['Hello'],
        'or_null_titles' => ['Hello'],
    ]);
});

// A native `array` or union return says less than a precise `@return`, which PHPStan reads as refining it.
it('reads a closure\'s precise @return over its native array or union return', function () {
    expect(publishedResourceKeyTypes(DeclaredClosureResource::class, Post::class))->toMatchArray([
        'shape_return?' => '{ a: number }',
        'union_return?' => '{ a: number }',
    ])->and(declaredClosureSends())->toMatchArray([
        'shape_return' => ['a' => 1],
        'union_return' => ['a' => 1],
    ]);
});
