<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\InBlockParameterReadsResource;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\Profile;
use Workbench\App\Models\User;

/**
 * What Laravel sends for the in-block fixture over a post (id 7) titled Banana, pinned or not, with comments 5 and 6
 * and its author Ann (id 3), whose profile is id 9.
 *
 * @return array<string, mixed>
 */
function inBlockSends(bool $pinned): array
{
    $post = new Post;
    $post->forceFill(['id' => 7, 'title' => 'Banana', 'is_pinned' => $pinned]);
    $post->setRelation('comments', new EloquentCollection([
        new Comment()->forceFill(['id' => 5, 'content' => 'hi']),
        new Comment()->forceFill(['id' => 6, 'content' => 'yo']),
    ]));
    $author = new User()->forceFill(['id' => 3, 'name' => 'Ann']);
    $author->setRelation('profile', new Profile()->forceFill(['id' => 9, 'bio' => 'b']));
    $post->setRelation('author', $author);
    /** @var array<string, mixed> $sent */
    $sent = json_decode((string) json_encode(new InBlockParameterReadsResource($post)->resolve(Request::create('/'))), true);

    return $sent;
}

// The block may not run, so the old value joins past it; a loop's earlier pass, a case falling through or the `if`
// condition may have written the read's value.
test('a parameter rewritten inside a block reads the written value in that block', function () {
    expect(publishedResourceKeyTypes(InBlockParameterReadsResource::class, Post::class))->toMatchArray([
        'inner_param?' => 'string | number',
        'inner_param_direct?' => '{ v: number } | { v: string }',
        'inner_loop?' => 'string | number',
        'loop_before?' => 'string | number',
        'inner_condition?' => 'string | number',
        'inner_case?' => 'string | number',
        'walk_key?' => '{ t: string | number }',
        'helper_untyped' => 'string | number',
    ])
        ->and(inBlockSends(true))->toMatchArray([
            'inner_param' => 7,
            'inner_param_direct' => ['v' => 7],
            'inner_loop' => 6,
            'loop_before' => 5,
            'inner_condition' => 7,
            'inner_case' => 7,
            'walk_key' => ['t' => 7],
            'helper_untyped' => 5,
        ])
        ->and(inBlockSends(false))->toMatchArray([
            'inner_param' => 'none',
            'inner_param_direct' => ['v' => 'x'],
            'inner_case' => 'Banana',
            'walk_key' => ['t' => 'none'],
            'helper_untyped' => 'none',
        ]);
});

// No earlier value reaches a read in the block past the write, so the untypable value leaves it unknown, as in a `try`.
test('a parameter rewritten inside a block with a value the engine cannot type reads unknown in that block', function () {
    expect(publishedResourceKeyTypes(InBlockParameterReadsResource::class, Post::class))->toMatchArray(['inner_decoded?' => 'unknown'])
        ->and(inBlockSends(true))->toMatchArray(['inner_decoded' => null])
        ->and(inBlockSends(false))->toMatchArray(['inner_decoded' => 'none']);
});

// A write naming no class keeps a relation's binding beside its value (O11), while a model's column read or another
// model replaces it.
test('a relation parameter rewritten inside a block joins the written value in that block', function () {
    expect(publishedResourceKeyTypes(InBlockParameterReadsResource::class, Post::class))->toMatchArray([
        'when_loaded_value?' => 'string | Comment[] | number',
        'in_model_column?' => 'string | number',
        'in_model_kept?' => 'string | User',
        'in_model_ended?' => 'Comment | null',
    ])
        ->and(inBlockSends(true))->toMatchArray([
            'when_loaded_value' => 2,
            'in_model_column' => 3,
            'in_model_kept' => ['id' => 3, 'name' => 'Ann', 'profile' => ['id' => 9, 'bio' => 'b']],
            'in_model_ended' => ['id' => 5, 'content' => 'hi'],
        ])
        ->and(inBlockSends(false))->toMatchArray([
            'when_loaded_value' => 'none',
            'in_model_column' => 'none',
            'in_model_kept' => 'none',
            'in_model_ended' => null,
        ]);
});

// A write naming another class reads the old binding too, so past its block the model joins the related one.
test('a parameter read inside a block before its write there reads the value it came in with', function () {
    expect(publishedResourceKeyTypes(InBlockParameterReadsResource::class, Post::class))->toMatchArray([
        'in_before?' => 'string',
        'past_ended?' => 'User | Profile | null',
        'past_ended_member?' => 'number',
    ])
        ->and(inBlockSends(true))
        ->toMatchArray(['in_before' => 'Banana', 'past_ended' => ['id' => 9, 'bio' => 'b'], 'past_ended_member' => 9])
        ->and(inBlockSends(false))->toMatchArray([
            'in_before' => 'none',
            'past_ended' => ['id' => 3, 'name' => 'Ann', 'profile' => ['id' => 9, 'bio' => 'b']],
            'past_ended_member' => 3,
        ]);
});

// A `try` body and an unbound local already read the write inside their block.
test('a parameter rewritten in a try body and a local rewritten in a block read the written value there', function () {
    expect(publishedResourceKeyTypes(InBlockParameterReadsResource::class, Post::class))->toMatchArray([
        'try_inner?' => 'number | string',
        'inner_local?' => 'string | number',
    ])
        ->and(inBlockSends(true))->toMatchArray(['try_inner' => 7, 'inner_local' => 7])
        ->and(inBlockSends(false))->toMatchArray(['try_inner' => 7, 'inner_local' => 'none']);
});
