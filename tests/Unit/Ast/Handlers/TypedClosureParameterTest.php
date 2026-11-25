<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\DeclaredClosureResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ParamTagShapesResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\TypedClosureParameterResource;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Stmt\Expression;
use Workbench\App\Http\Resources\DocblockReads\ParamTagClassesResource;
use Workbench\App\Models\Comment;
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
    $post = new Post()->forceFill(['id' => 1, 'title' => 'Hello'])
        ->setRelation('comments', new EloquentCollection([new Comment()->forceFill(['id' => 5, 'content' => 'hi', 'post_id' => 1])]));

    return json_decode((string) json_encode(new DeclaredClosureResource($post)->resolve(request())), true);
}

// A value closure's `@param` names its class as a native type would where the element names none, and gives way to
// the element's; it names no global class the file does not import. A collected value nothing types may hold string
// keys, which all() keeps.
it('binds a value closure parameter by its @param class where the element names none', function () {
    expect(publishedResourceKeyTypes(DeclaredClosureResource::class, Post::class))->toMatchArray([
        'native_param_titles' => 'string[] | Record<string, string>',
        'doc_param_titles' => 'string[] | Record<string, string>',
        'doc_param_item_ids' => 'number[] | Record<string, number>',
        'nullable_titles' => '(string | null)[] | Record<string, string | null>',
        'doc_param_over_comments' => 'number[]',
        'doc_param_global' => 'unknown',
    ])->and(declaredClosureSends())->toMatchArray([
        'native_param_titles' => ['Hello'],
        'doc_param_titles' => ['Hello'],
        'doc_param_item_ids' => [7],
        'nullable_titles' => ['Hello'],
        'doc_param_over_comments' => [1],
        'doc_param_global' => [now()->format('Y')],
    ]);
});

/**
 * What Laravel sends for a resource over no model, decoded from its JSON.
 *
 * @param  class-string<JsonResource>  $resource
 * @return array<string, mixed>
 */
function paramTagSends(string $resource): array
{
    /** @var array<string, mixed> */
    return json_decode((string) json_encode(new $resource(null)->resolve(request())), true);
}

// A `@param` names the receiver as its native type does, even where the class's own reading cannot publish.
it('binds a value closure parameter by an @param naming a class that is not a model', function (string $resource, string $pair, string $type, array $sent) {
    expect(analyzedKeyTypes($resource))
        ->toHaveKey("{$pair}_by_param_tag", $type)
        ->toHaveKey("{$pair}_by_native_type", $type)
        ->and(paramTagSends($resource))
        ->toHaveKey("{$pair}_by_param_tag", $sent)
        ->toHaveKey("{$pair}_by_native_type", $sent);
})->with([
    'a value object with private state' => [ParamTagClassesResource::class, 'private_amounts', 'string[] | Record<string, string>', ['5.00']],
    'an interface' => [ParamTagClassesResource::class, 'interface_labels', 'string[] | Record<string, string>', ['a']],
    'BackedEnum' => [ParamTagClassesResource::class, 'backed_enum_values', '(string | number)[] | Record<string, string | number>', [1]],
    'UnitEnum' => [ParamTagClassesResource::class, 'unit_enum_names', 'string[] | Record<string, string>', ['Published']],
    'a bare Collection' => [ParamTagClassesResource::class, 'collection_counts', 'number[] | Record<string, number>', [2]],
    'an abstract class that is not a model' => [ParamTagClassesResource::class, 'abstract_areas', 'number[] | Record<string, number>', [4]],
    'a class with an untyped public property' => [ParamTagClassesResource::class, 'loose_labels', 'string[] | Record<string, string>', ['L']],
    'a value object read by a numeric method' => [ParamTagShapesResource::class, 'square_areas', 'number[] | Record<string, number>', [4]],
    'a nullable interface' => [ParamTagShapesResource::class, 'nullable_labels', '(string | null)[] | Record<string, string | null>', ['a']],
    'a collection sent without all()' => [ParamTagShapesResource::class, 'amount_collection', 'string[] | Record<string, string>', ['5.00']],
    'an array_map() callback' => [ParamTagShapesResource::class, 'mapped_amounts', 'string[] | Record<string, string>', ['5.00']],
    'a statement closure' => [ParamTagShapesResource::class, 'statement_amounts', 'string[] | Record<string, string>', ['5.00']],
    'a shape' => [ParamTagShapesResource::class, 'label_rows', '{ label: string }[] | Record<string, { label: string }>', [['label' => 'a']]],
    'an enum\'s cases' => [
        ParamTagShapesResource::class,
        'case_rows',
        '{ value: string | number; name: string }[] | Record<string, { value: string | number; name: string }>',
        [['value' => 0, 'name' => 'Draft'], ['value' => 1, 'name' => 'Published']],
    ],
    'an options builder' => [
        ParamTagShapesResource::class,
        'enum_options',
        '{ value: string | number; name: string }[] | Record<string, { value: string | number; name: string }>',
        [['value' => 0, 'name' => 'Draft'], ['value' => 1, 'name' => 'Published']],
    ],
    'an options builder re-indexed into a list' => [
        ParamTagShapesResource::class,
        'enum_option_list',
        '{ value: string | number; name: string }[]',
        [['value' => 0, 'name' => 'Draft'], ['value' => 1, 'name' => 'Published']],
    ],
    'a helper the resource spreads' => [ParamTagShapesResource::class, 'helper_amounts', 'string[] | Record<string, string>', ['5.00']],
    'a nested key' => [ParamTagShapesResource::class, 'nested_labels', '{ labels: string[] | Record<string, string> }', ['labels' => ['a']]],
]);

// Of a `@param` and the class a property declares its element as, the narrower binds.
it('binds a value closure parameter by the narrower of its @param and its element\'s declared class', function () {
    expect(analyzedKeyTypes(ParamTagShapesResource::class))->toMatchArray([
        'declared_element_labels' => 'string[]',
        'declared_element_mapped_labels' => 'string[]',
        'declared_element_reports' => '{ total: number }[]',
        'declared_wider_element_labels' => 'string[]',
    ]);
});

// A union spelled `X|null` names its class as `?X` does; Pint rewrites the spelling in a fixture, so it is parsed here.
it('binds a closure parameter typed X|null by its class', function () {
    $statement = new AstParser()->parseSource('<?php function (\\Workbench\\App\\Models\\Post|null $post) { return $post?->title; };')[0];
    $scope = new AnalysisScope(new ReflectionClass(DeclaredClosureResource::class), Post::class);
    $engine = new ResourceAstAnalyzer(new ReflectionClass(DeclaredClosureResource::class), Post::class, 'toArray', null, $scope);

    expect($statement)->toBeInstanceOf(Expression::class)
        ->and($engine->resolve($statement->expr)['type'])->toBe('string | null');
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
