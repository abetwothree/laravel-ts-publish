<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\DefaultBoundReceiverResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * What Laravel sends for DefaultBoundReceiverResource over a saved, unrated post whose one comment is loaded.
 *
 * @return array<string, mixed>
 */
function defaultBoundReceiverSends(): array
{
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);
    Comment::create(['content' => 'first', 'post_id' => $post->id, 'user_id' => $user->id]);

    $resource = new DefaultBoundReceiverResource(Post::with('comments')->findOrFail($post->id));

    /** @var array{data: array<string, mixed>} $sent */
    $sent = json_decode((string) $resource->response(request())->getContent(), true);

    return $sent['data'];
}

// A new post has no author and no key, and whenNotNull() always calls a closure, so every key is sent, null included.
it('reads a receiver bound to a default, a whenNotNull() closure and a collection a helper is passed', function () {
    expect(publishedResourceKeyTypes(DefaultBoundReceiverResource::class, Post::class))->toBe([
        'default_author?' => 'string | null',
        'default_key?' => 'number | null',
        'not_null_author' => 'string | null',
        'not_null_status' => "'Draft' | 'Published'",
        'not_null_rating' => 'string | null',
        'not_null_callable' => 'string | null',
        'callable_first?' => '{ first: string | null }',
        'helper_first' => '{ first: string | null }',
        'closure_first?' => '{ first: string | null }',
    ])
        ->and(defaultBoundReceiverSends())->toBe([
            'default_author' => null,
            'default_key' => null,
            'not_null_author' => null,
            'not_null_status' => 'Draft',
            'not_null_rating' => null,
            'not_null_callable' => null,
            'callable_first' => ['first' => 'first'],
            'helper_first' => ['first' => 'first'],
            'closure_first' => ['first' => 'first'],
        ]);
});
