<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MapElementHintsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MapReceiverEdgesResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReplacedParametersResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

test('a map over no collection, or over values a write replaced, publishes no list of the closure shape', function () {
    $types = publishedResourceKeyTypes(MapReceiverEdgesResource::class, Post::class);

    expect($types['box'])->toBe('{ items: unknown[] }')
        ->and($types['literal'])->toBe('unknown')
        ->and($types['plucked?'])->toBe('unknown')
        ->and($types['paged'])->toBe('{ id: number }[]');
});

/**
 * What Laravel sends for ReplacedParametersResource over the post `First` with options `a` and an empty `b`, its author
 * Ada and her comments 1 `alpha` and 2 `beta`, all loaded.
 *
 * @return array<string, mixed>
 */
function replacedParametersSends(): array
{
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id, 'options' => ['a' => 'x', 'b' => '']]);

    foreach ([1 => 'alpha', 2 => 'beta'] as $id => $content) {
        new Comment()->forceFill(['id' => $id, 'content' => $content, 'post_id' => $post->id, 'user_id' => $user->id])->save();
    }

    /** @var array{data: array<string, mixed>} $sent */
    $sent = json_decode((string) new ReplacedParametersResource(Post::with(['author.comments', 'comments'])->findOrFail($post->id))
        ->response(request())->getContent(), true);

    return $sent['data'];
}

test('a map passes the values a replaced collection holds, and a replaced parameter reads what the body wrote', function () {
    $types = publishedResourceKeyTypes(ReplacedParametersResource::class, Post::class);
    $sent = replacedParametersSends();

    expect($types)->toMatchArray([
        'plucked_ids?' => 'number[]',
        'mapped_contents?' => 'string[]',
        'keyed_all?' => 'Comment[] | Record<string, Comment>',
        'resorted_ids?' => 'number[] | Record<string, number>',
        'found_content?' => 'string | null',
        'found_name?' => 'unknown',
        'found_bare?' => 'unknown',
        'decoded?' => 'unknown',
    ])->and($sent)->toMatchArray([
        'plucked_ids' => [1, 2],
        'mapped_contents' => ['alpha', 'beta'],
        'resorted_ids' => [1 => 2, 0 => 1],
        'found_content' => 'alpha',
        'found_name' => null,
        'decoded' => null,
    ])->and(array_keys($sent['keyed_all']))->toBe(['alpha', 'beta'])
        ->and(array_keys($sent['resorted_ids']))->toBe([1, 0])
        ->and($sent['found_bare'])->toMatchArray(['id' => 1, 'content' => 'alpha']);
});

test('a write that keeps the loaded comments keeps the binding, and a plain value handed back changed reads the write', function () {
    $types = publishedResourceKeyTypes(ReplacedParametersResource::class, Post::class);
    $sent = replacedParametersSends();

    expect($types)->toMatchArray([
        'merged_contents?' => 'string[]',
        'collected_contents?' => 'string[]',
        'wrapped_contents?' => 'string[]',
        'made_contents?' => 'string[]',
        'defaulted_contents?' => 'string[]',
        'based_contents?' => 'string[]',
        'lazy_contents?' => 'string[]',
        'trimmed?' => 'string',
        'upper?' => 'string',
        'same?' => 'string',
        'trimmed_upper?' => 'string',
        'filtered_options?' => 'Record<string, string>',
    ])->and($sent)->toMatchArray([
        'merged_contents' => ['alpha', 'beta'],
        'collected_contents' => ['alpha', 'beta'],
        'wrapped_contents' => ['alpha', 'beta'],
        'made_contents' => ['alpha', 'beta'],
        'defaulted_contents' => ['alpha', 'beta'],
        'based_contents' => ['alpha', 'beta'],
        'lazy_contents' => ['alpha', 'beta'],
        'trimmed' => 'First',
        'upper' => 'FIRST',
        'same' => 'First',
        'trimmed_upper' => 'FIRST',
        'filtered_options' => ['a' => 'x'],
    ]);
});

/**
 * What Laravel sends for MapElementHintsResource over the post `First` with the comment `alpha`, under `with` with its
 * author Ada loaded, and under `without` with the author loaded as null.
 *
 * @return array{with: array<string, mixed>, without: array<string, mixed>}
 */
function mapElementHintsSends(): array
{
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);
    new Comment()->forceFill(['id' => 1, 'content' => 'alpha', 'post_id' => $post->id, 'user_id' => $user->id])->save();
    $send = function (Post $post): array {
        /** @var array{data: array<string, mixed>} $sent */
        $sent = json_decode((string) new MapElementHintsResource($post)->response(request())->getContent(), true);

        return $sent['data'];
    };

    return [
        'with' => $send(Post::with(['author.posts', 'comments'])->findOrFail($post->id)),
        'without' => $send(Post::with('comments')->findOrFail($post->id)->setRelation('author', null)),
    ];
}

test('a map behind a ?-> adds the null its short circuit sends', function () {
    $types = publishedResourceKeyTypes(MapElementHintsResource::class, Post::class);
    $sent = mapElementHintsSends();

    expect($types['author_posts'])->toBe('{ id: number }[] | null')
        ->and($types['author_posts_all'])->toBe('{ id: number }[] | null')
        ->and($sent['with']['author_posts'])->toBe([['id' => 1]])
        ->and($sent['without'])->toMatchArray(['author_posts' => null, 'author_posts_all' => null]);
});

test('a parameter declaring a supertype of the element reads the element\'s own model', function () {
    $types = publishedResourceKeyTypes(MapElementHintsResource::class, Post::class);
    $sent = mapElementHintsSends()['with'];

    expect($types['model_hint_chain'])->toBe('string[]')
        ->and($types['model_hint_collect'])->toBe('string[]')
        ->and($types['abstract_hint_query'])->toBe('string[]')
        ->and($sent['model_hint_chain'])->toBe(['alpha'])
        ->and($sent['model_hint_collect'])->toBe(['alpha'])
        ->and($sent['abstract_hint_query'])->toBe(['First']);
});
