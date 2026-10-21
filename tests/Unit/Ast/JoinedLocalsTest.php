<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JoinedLocalsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JoinedParametersResource;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;

/**
 * A post titled Banana, pinned or not, with one comment `hi` (id 5), as the joined fixtures read it.
 */
function joinedPost(bool $pinned): Post
{
    $comment = new Comment;
    $comment->forceFill(['id' => 5, 'content' => 'hi']);
    $post = new Post;
    $post->forceFill(['id' => 7, 'title' => 'Banana', 'is_pinned' => $pinned]);
    $post->setRelation('comments', new EloquentCollection([$comment]));

    return $post;
}

/**
 * What Laravel sends for a joined fixture over the post, pinned or not.
 *
 * @param  class-string<JoinedLocalsResource|JoinedParametersResource>  $resource
 * @return array<string, mixed>
 */
function joinedSends(string $resource, bool $pinned): array
{
    /** @var array<string, mixed> $sent */
    $sent = json_decode((string) json_encode(new $resource(joinedPost($pinned))->resolve(Request::create('/'))), true);

    return $sent;
}

test('a local written in sequence and inside branches joins the values that can reach the read', function () {
    expect(publishedResourceKeyTypes(JoinedLocalsResource::class, Post::class))->toMatchArray([
        'status' => 'string | number',
        'first' => 'string | null',
        'removed' => 'unknown',
        'captured' => 'unknown',
    ])
        ->and(joinedSends(JoinedLocalsResource::class, true))->toMatchArray(['status' => 1, 'first' => 'hi'])
        ->and(joinedSends(JoinedLocalsResource::class, false))->toMatchArray(['status' => 'draft', 'removed' => 'kept', 'captured' => 'kept']);
});

test('a local the try body and a catch both assign joins both, and a finally write replaces them', function () {
    expect(publishedResourceKeyTypes(JoinedLocalsResource::class, Post::class))->toMatchArray([
        'caught' => 'string | null',
        'finally' => 'number',
        'caught_read' => 'string | number',
    ])
        ->and(joinedSends(JoinedLocalsResource::class, true))->toMatchArray(['caught' => null, 'finally' => 1, 'caught_read' => 'Banana'])
        ->and(joinedSends(JoinedLocalsResource::class, false))->toMatchArray(['caught' => 'Banana', 'finally' => 1, 'caught_read' => 7]);
});

// An old value no rule types keeps the joined read unknown. A call no rule types binds the callback's parameter to
// nothing, so the read past the block is a local's that one nested write leaves unbound.
test('a parameter written inside a block joins its old value and the write\'s', function () {
    expect(publishedResourceKeyTypes(JoinedParametersResource::class, Post::class))->toMatchArray([
        'joined?' => 'string | number',
        'vague_joined?' => 'unknown',
        'opaque_joined?' => 'unknown',
    ])
        ->and(joinedSends(JoinedParametersResource::class, true))->toMatchArray(['joined' => 5, 'vague_joined' => 5, 'opaque_joined' => 5]);
});

test('a local that reads itself in its own reassignment keeps both values', function () {
    expect(publishedResourceKeyTypes(JoinedLocalsResource::class, Post::class))->toMatchArray([
        'self' => 'string',
        'wrapped' => '{ v: string }',
    ])
        ->and(joinedSends(JoinedLocalsResource::class, false))->toMatchArray(['self' => 'aBanana', 'wrapped' => ['v' => 'Banana']]);
});
