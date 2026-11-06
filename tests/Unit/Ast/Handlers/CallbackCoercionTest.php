<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\CallbackCoercionResource;
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

// transform() calls any callable, so a function's name or a pair is the call it names; a callable a property or a
// ternary holds says no one call, so it stays unknown.
it('types a transform() callback spelled as a function name or a pair as the call it names', function () {
    $sent = callbackCoercionSent();

    expect(publishedResourceKeyTypes(CallbackCoercionResource::class, Post::class))->toMatchArray([
        'transform_function?' => 'number',
        'transform_pair?' => 'number',
        'transform_property?' => 'unknown',
        'transform_ternary?' => 'unknown',
    ])->and([$sent['transform_function'], $sent['transform_pair'], $sent['transform_property'], $sent['transform_ternary']])
        ->toBe([5, 5, 5, 5]);
});

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
