<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\CollectionVariableFilterResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * What Laravel sends for CollectionVariableFilterResource over a post whose comments 1, 2 and 3 are loaded: each key's
 * comment contents when it sends a list, else `keyed`.
 *
 * @return array<string, list<string>|string>
 */
function collectionVariableFilterSends(): array
{
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);

    foreach ([1 => 'one', 2 => 'two', 3 => 'three'] as $id => $content) {
        new Comment()->forceFill(['id' => $id, 'content' => $content, 'post_id' => $post->id, 'user_id' => $user->id])->save();
    }

    $resource = new CollectionVariableFilterResource(Post::with('comments')->findOrFail($post->id));

    /** @var array{data: array<string, array<array-key, array{content: string}>>} $sent */
    $sent = json_decode((string) $resource->response(request())->getContent(), true);

    return array_map(
        fn (array $comments): array|string => array_is_list($comments) ? array_column($comments, 'content') : 'keyed',
        $sent['data'],
    );
}

// Eloquent\Collection::only() and except() keep whole models by primary key and list them, as on the relation itself;
// passed null, only() keeps every model under the key it had.
it('lists the models only() and except() keep on a variable holding an Eloquent collection', function () {
    expect(publishedResourceKeyTypes(CollectionVariableFilterResource::class, Post::class))->toBe([
        'only?' => 'Comment[]',
        'except?' => 'Comment[]',
        'local_only' => 'Comment[]',
        'relation_only' => 'Comment[]',
        'keyed_only' => 'Comment[]',
        'keyed_only_null' => 'Comment[] | Record<string, Comment>',
    ])
        ->and(collectionVariableFilterSends())->toBe([
            'only' => ['one', 'two'],
            'except' => ['two', 'three'],
            'local_only' => ['one'],
            'relation_only' => ['one', 'two'],
            'keyed_only' => ['one'],
            'keyed_only_null' => 'keyed',
        ]);
});
