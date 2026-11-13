<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\CallbackCoercionResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\TransformCallableSourcesChildResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\TransformCallableSourcesResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\CommentRack;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Workbench\App\Models\Post;

/**
 * What json_encode() sends for each key of the resource over a post with one comment.
 *
 * @return array<string, mixed>
 */
function callbackCoercionSent(): array
{
    $post = (new Post)->forceFill(['id' => 7, 'title' => 'Hello', 'comments_exists' => true])
        ->setRelation('comments', new EloquentCollection([CommentRack::comment(11)]));

    return json_decode((string) json_encode(CallbackCoercionResource::make($post)->resolve()), true);
}

// Laravel and PHP's own functions call a callback in coercive mode, so a scalar parameter holds the value converted to
// its type: a model to its JSON string, a number to a string; an int-backed enum's value and a type the app declares
// for a class stay as they are.
it('types a scalar callback parameter as the type PHP converts the passed value to', function () {
    $sent = callbackCoercionSent();
    $comment = json_encode(CommentRack::comment(11));

    expect(publishedResourceKeyTypes(CallbackCoercionResource::class, Post::class))->toMatchArray([
        'enum_value_int' => 'StatusType[]',
        'enum_value_array_map_int' => 'StatusType[]',
        'enum_value_string' => 'string[]',
        'enum_value_or_zero' => '(StatusType | number)[]',
        'alias_as_string' => 'AliasedTokenType[]',
        'alias_transformed?' => 'AliasedTokenType',
        'models_as_strings' => 'string[]',
        'models_mapped_as_strings' => 'string[]',
        'when_has_string?' => 'string',
        'transform_string?' => 'string',
        'exists_loaded_string?' => 'string',
    ])->and([$sent['enum_value_int'], $sent['enum_value_array_map_int'], $sent['enum_value_string']])->toBe([[1], [1], ['1']])
        ->and($sent['enum_value_or_zero'])->toBe([0])
        ->and([$sent['alias_as_string'], $sent['alias_transformed']])->toBe([['a'], 'a'])
        ->and([$sent['models_as_strings'], $sent['models_mapped_as_strings']])->toBe([[$comment], [$comment]])
        ->and([$sent['when_has_string'], $sent['transform_string'], $sent['exists_loaded_string']])->toBe(['7', '7', '1']);
});

// transform() calls any callable, so a function's name or a pair is the call it names, and a callable a property, a
// ternary or a join holds is the union of the calls its branches name.
it('types a transform() callback spelled as a function name or a pair as the call it names', function () {
    $sent = callbackCoercionSent();
    $unsized = json_decode((string) json_encode(CallbackCoercionResource::make(
        (new Post)->forceFill(['id' => 0, 'title' => 'hello', 'comments_exists' => true])
            ->setRelation('comments', new EloquentCollection([CommentRack::comment(11)])),
    )->resolve()), true);

    expect(publishedResourceKeyTypes(CallbackCoercionResource::class, Post::class))->toMatchArray([
        'transform_function?' => 'number',
        'transform_pair?' => 'number',
        'transform_property?' => 'number',
        'transform_ternary?' => 'number | string',
        'transform_written_property?' => 'string | number',
        'transform_two_writes?' => 'number',
        'transform_closure_ternary?' => 'number',
    ])->and([$sent['transform_function'], $sent['transform_pair'], $sent['transform_property'], $sent['transform_ternary']])
        ->toBe([5, 5, 5, 5])
        ->and([$sent['transform_written_property'], $sent['transform_two_writes'], $sent['transform_closure_ternary']])
        ->toBe(['HELLO', 2, 1])
        ->and([$unsized['transform_ternary'], $unsized['transform_two_writes'], $unsized['transform_closure_ternary']])
        ->toBe(['Hello', 1, 2]);
});

// A constant or a method's literal return names the call transform() makes, and `??` runs its right arm only where the
// left may be null; a callable no literal names, as a parameter or a config() value holds, reads as the value it is.
it('types a transform() callback a constant or a literal return names as that call, and keeps any other as before', function (string $resource, array $types, array $sent) {
    $post = (new Post)->forceFill(['id' => 7, 'title' => 'Hello']);

    expect(publishedResourceKeyTypes($resource, Post::class))->toBe($types)
        ->and(json_decode((string) json_encode($resource::make($post)->resolve()), true))->toBe($sent);
})->with([
    'the resource' => [TransformCallableSourcesResource::class, [
        'class_const?' => 'number',
        'method_string?' => 'number',
        'coalesce_method?' => 'string',
        'coalesce_fall_off?' => 'string | number',
        'string_param?' => 'string',
        'config_string?' => 'string',
        'dim_rewritten?' => 'string',
        'own_property?' => 'number',
    ], [
        'class_const' => 5,
        'method_string' => 5,
        'coalesce_method' => 'Hello',
        'coalesce_fall_off' => 5,
        'string_param' => 5,
        'config_string' => 5,
        'dim_rewritten' => 'Hello',
        'own_property' => 5,
    ]],
    'a subclass redeclaring its property' => [TransformCallableSourcesChildResource::class, [
        'class_const?' => 'number',
        'method_string?' => 'number',
        'coalesce_method?' => 'string',
        'coalesce_fall_off?' => 'string | number',
        'string_param?' => 'string',
        'config_string?' => 'string',
        'dim_rewritten?' => 'string',
        'own_property?' => 'string',
    ], [
        'class_const' => 5,
        'method_string' => 5,
        'coalesce_method' => 'Hello',
        'coalesce_fall_off' => 5,
        'string_param' => 5,
        'config_string' => 5,
        'dim_rewritten' => 'Hello',
        'own_property' => 'Hello',
    ]],
]);

// A join that left a write out still holds the collection it read, as the run output warns; a value nothing types
// may be a list or a record, so map() keeps either.
it('maps a local a join holds as the collection it read, and a value nothing types as a list or a record', function () {
    $sent = callbackCoercionSent();

    expect(publishedResourceKeyTypes(CallbackCoercionResource::class, Post::class))->toMatchArray([
        'joined_typed' => 'string[]',
        'joined_untyped' => 'string[]',
        'opaque_typed' => 'number[] | Record<string, number>',
    ])->and([$sent['joined_typed'], $sent['joined_untyped'], $sent['opaque_typed']])
        ->toBe([[CommentRack::comment(11)->content], [CommentRack::comment(11)->content], ['first' => 11]])
        ->and(json_encode(array_column(AnalysisWarnings::all(), 'message')))->toContain('leaves out `$this->opaque($this->resource->comments)`');
});
