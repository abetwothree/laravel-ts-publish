<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JoinedDeclaredLocalsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JoinedLocalsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JoinedNullMemberResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JoinedParametersResource;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * A post titled Banana, pinned or not, with options a=x and an empty b, one comment `hi` (id 5) and its author Ann.
 */
function joinedPost(bool $pinned): Post
{
    $comment = new Comment;
    $comment->forceFill(['id' => 5, 'content' => 'hi']);
    $post = new Post;
    $post->forceFill(['id' => 7, 'title' => 'Banana', 'is_pinned' => $pinned, 'options' => ['a' => 'x', 'b' => '']]);
    $post->setRelation('comments', new EloquentCollection([$comment]));
    $post->setRelation('author', new User()->forceFill(['id' => 3, 'name' => 'Ann', 'email' => 'ann@example.com']));

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

/**
 * A user (id 3) with one post titled Banana (id 7), or a guest (id 0) with none.
 */
function joinedUser(bool $guest): User
{
    $post = new Post;
    $post->forceFill(['id' => 7, 'title' => 'Banana']);
    $user = new User()->forceFill(['id' => $guest ? 0 : 3, 'name' => 'Ann']);
    $user->setRelation('posts', new EloquentCollection($guest ? [] : [$post]));

    return $user;
}

/**
 * What Laravel sends for the declared-locals fixture over a user or a guest.
 *
 * @return array<string, mixed>
 */
function joinedDeclaredSends(bool $guest): array
{
    $resource = new JoinedDeclaredLocalsResource(joinedUser($guest));
    /** @var array<string, mixed> $sent */
    $sent = json_decode((string) json_encode($resource->resolve(Request::create('/'))), true);

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

// `?->` reads a joined `null` as null, so the `null` adds no class to the receiver, written before the model or after.
test('a member read past a join leaves out a joined null', function () {
    expect(publishedResourceKeyTypes(JoinedLocalsResource::class, Post::class))
        ->toMatchArray(['author_name' => 'string | null', 'editor_name' => 'string | null'])
        ->and(joinedSends(JoinedLocalsResource::class, true))
        ->toMatchArray(['author_name' => 'Ann', 'editor_name' => null])
        ->and(joinedSends(JoinedLocalsResource::class, false))
        ->toMatchArray(['author_name' => null, 'editor_name' => 'Ann']);
});

// On the `null` path `->` raises a warning Laravel throws as an ErrorException, so only the model's member is sent.
test('a plain member read past a joined null reads the model\'s member', function () {
    $request = Request::create('/');

    expect(publishedResourceKeyTypes(JoinedNullMemberResource::class, Post::class))->toBe(['editor_name' => 'string'])
        ->and(fn () => new JoinedNullMemberResource(joinedPost(true))->resolve($request))
        ->toThrow(ErrorException::class, 'Attempt to read property "name" on null')
        ->and(new JoinedNullMemberResource(joinedPost(false))->resolve($request))->toBe(['editor_name' => 'Ann']);
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

// An old value no rule types is left out beside the write's, as a ternary leaves out an arm, and so is the value of a
// call no rule types, which binds the callback's parameter to nothing.
test('a parameter written inside a block joins its old value and the write\'s', function () {
    expect(publishedResourceKeyTypes(JoinedParametersResource::class, Post::class))->toMatchArray([
        'joined?' => 'string | number',
        'vague_joined?' => 'number',
        'opaque_joined?' => 'number',
        'caught_joined?' => 'string | number',
    ])
        ->and(joinedSends(JoinedParametersResource::class, true))
        ->toMatchArray(['joined' => 5, 'vague_joined' => 5, 'opaque_joined' => 5, 'caught_joined' => 'Banana'])
        ->and(joinedSends(JoinedParametersResource::class, false))->toMatchArray(['caught_joined' => 3]);
});

// A variable passed in a gap whose old value may still hold passes both, for the value and for a receiver alike.
test('a parameter passed on past a nested write passes its old value and the write\'s', function () {
    expect(publishedResourceKeyTypes(JoinedParametersResource::class, Post::class))->toMatchArray([
        'passed_joined?' => 'string | number',
        'passed_joined_id?' => 'number',
    ])
        ->and(joinedSends(JoinedParametersResource::class, true))->toMatchArray(['passed_joined' => 7, 'passed_joined_id' => 5])
        ->and(joinedSends(JoinedParametersResource::class, false))->toMatchArray(['passed_joined' => 'Banana', 'passed_joined_id' => 3]);
});

// The value a parameter's own write left still reaches past a nested write, so one the engine cannot type is left out.
// With no earlier value reaching, past a top-level write or a `try` and `catch` that both write, it reads unknown.
test('a parameter past its own write leaves out a nested write the engine cannot type', function () {
    expect(publishedResourceKeyTypes(JoinedParametersResource::class, Post::class))->toMatchArray([
        'post_write_nested?' => 'string',
        'post_write_nested_typed?' => 'string | number',
        'both_paths?' => 'unknown',
        'expr_value?' => 'unknown',
    ])
        ->and(joinedSends(JoinedParametersResource::class, true))
        ->toMatchArray(['post_write_nested' => null, 'post_write_nested_typed' => 6, 'both_paths' => [], 'expr_value' => 's'])
        ->and(joinedSends(JoinedParametersResource::class, false))
        ->toMatchArray(['post_write_nested' => 'Banana', 'post_write_nested_typed' => 'Banana']);
});

// Each arm a join leaves out is the audit's: the old value no rule types, a property's or the value of a call no rule
// types, and a nested write no rule types while an earlier value still reaches the read.
test('a parameter\'s join records each arm it leaves out', function () {
    DroppedUnionArms::start();

    try {
        publishedResourceKeyTypes(JoinedParametersResource::class, Post::class);
    } finally {
        $dropped = DroppedUnionArms::stop();
    }

    expect(array_map(fn (array $arm): string => $arm['line'].' '.$arm['expression'].' '.$arm['site'], $dropped))->toBe([
        '44 $this->vague binding-join',
        '71 json_decode(\'{}\') binding-join',
        '76 $this->opaque() binding-join',
        '137 json_decode($title) binding-join',
    ]);
});

// The declared string holds for a read before the body's write, and joins a branch's write for a read past it.
test('a helper\'s parameter the body writes reads its declared value before the write, and a branch\'s write past it', function () {
    expect(publishedResourceKeyTypes(JoinedParametersResource::class, Post::class))->toMatchArray([
        'helper_joined' => 'string | number',
        'helper_copy' => 'string',
    ])
        ->and(joinedSends(JoinedParametersResource::class, true))->toMatchArray(['helper_joined' => 7, 'helper_copy' => 'Banana'])
        ->and(joinedSends(JoinedParametersResource::class, false))->toMatchArray(['helper_joined' => 'Banana']);
});

// The tag names another model, so past the block a read joins the old model and the tag's, for a member and a value.
test('a parameter joins the model a nested @var declares for its write past its block', function () {
    expect(publishedResourceKeyTypes(JoinedParametersResource::class, Post::class))
        ->toMatchArray(['declared_joined_id?' => 'number | null', 'declared_joined?' => 'User | Comment | null'])
        ->and(joinedSends(JoinedParametersResource::class, true))
        ->toMatchArray(['declared_joined_id' => 5, 'declared_joined' => ['id' => 5, 'content' => 'hi']])
        ->and(joinedSends(JoinedParametersResource::class, false))->toMatchArray([
            'declared_joined_id' => 3,
            'declared_joined' => ['id' => 3, 'name' => 'Ann', 'email' => 'ann@example.com'],
        ]);
});

// A written value no rule types is left out of the join, as a receiver keeps the old binding past it.
test('a parameter keeps its old value past a nested write the engine cannot type', function () {
    expect(publishedResourceKeyTypes(JoinedParametersResource::class, Post::class))->toMatchArray([
        'kept_upper?' => 'string',
        'kept_filtered?' => 'Record<string, string> | null',
        'kept_author?' => 'User',
    ])
        ->and(joinedSends(JoinedParametersResource::class, true))
        ->toMatchArray(['kept_upper' => 'BANANA', 'kept_filtered' => ['a' => 'x'], 'kept_author' => []])
        ->and(joinedSends(JoinedParametersResource::class, false))
        ->toMatchArray(['kept_upper' => 'Banana', 'kept_filtered' => ['a' => 'x', 'b' => ''], 'kept_author' => ['id' => 3, 'name' => 'Ann', 'email' => 'ann@example.com']]);
});

test('a nested @var types the value a join carries past its block', function () {
    expect(publishedResourceKeyTypes(JoinedDeclaredLocalsResource::class, User::class))
        ->toMatchArray(['t' => 'string | null', 'post' => 'Post | null'])
        ->and(joinedDeclaredSends(false))->toMatchArray(['t' => 'Banana', 'post' => ['id' => 7, 'title' => 'Banana']])
        ->and(joinedDeclaredSends(true))->toMatchArray(['t' => null, 'post' => null]);
});

// data_get() of one key reads the relation, which the value takes; the tag types the receiver it names nothing for.
test('a nested @var naming a collection types the collection a join carries past its block', function () {
    expect(publishedResourceKeyTypes(JoinedDeclaredLocalsResource::class, User::class))
        ->toMatchArray(['posts' => 'Post[] | null', 'posts_first_title' => 'string | null'])
        ->and(joinedDeclaredSends(false))
        ->toMatchArray(['posts' => [['id' => 7, 'title' => 'Banana']], 'posts_first_title' => 'Banana'])
        ->and(joinedDeclaredSends(true))->toMatchArray(['posts' => null, 'posts_first_title' => null]);
});

// The tag declares `$other`, not `$o`, so the join reads data_get() alone, though Laravel sends the title.
test('a nested @var naming another variable leaves the join\'s own reading', function () {
    expect(publishedResourceKeyTypes(JoinedDeclaredLocalsResource::class, User::class))
        ->toMatchArray(['other_title' => 'unknown'])
        ->and(joinedDeclaredSends(false))->toMatchArray(['other_title' => 'Banana'])
        ->and(joinedDeclaredSends(true))->toMatchArray(['other_title' => null]);
});

test('a local another name or a closure can write through a reference is never joined', function () {
    expect(publishedResourceKeyTypes(JoinedLocalsResource::class, Post::class))->toMatchArray([
        'aliased' => 'unknown',
        'closed' => 'unknown',
    ])
        ->and(joinedSends(JoinedLocalsResource::class, false))->toMatchArray(['aliased' => 1, 'closed' => 1]);
});

test('a variable variable\'s write is no write of the variable that names it', function () {
    expect(publishedResourceKeyTypes(JoinedLocalsResource::class, Post::class))->toMatchArray(['key' => 'string'])
        ->and(joinedSends(JoinedLocalsResource::class, false))->toMatchArray(['key' => 'a']);
});

test('a local that reads itself in its own reassignment keeps both values', function () {
    expect(publishedResourceKeyTypes(JoinedLocalsResource::class, Post::class))->toMatchArray([
        'self' => 'string',
        'wrapped' => '{ v: string }',
    ])
        ->and(joinedSends(JoinedLocalsResource::class, false))->toMatchArray(['self' => 'aBanana', 'wrapped' => ['v' => 'Banana']]);
});
