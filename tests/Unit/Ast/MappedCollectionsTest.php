<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\MappedCollectionsResource;
use Workbench\App\Http\Resources\PostCollection;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * What Laravel sends for MappedCollectionsResource over a post by Ada whose comments `alpha` and `beta` she wrote, but
 * `all_posts`, whose PostResource reads columns the seeded post leaves empty: that key holds the collection itself.
 *
 * @return array<string, mixed>
 */
function mappedCollectionsSends(): array
{
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);

    foreach ([1 => 'alpha', 2 => 'beta'] as $id => $content) {
        new Comment()->forceFill(['id' => $id, 'content' => $content, 'post_id' => $post->id, 'user_id' => $user->id])->save();
    }

    $data = new MappedCollectionsResource(Post::with(['author', 'comments'])->findOrFail($post->id))->toArray(request());
    $allPosts = $data['all_posts'];
    unset($data['all_posts']);

    $sent = new class($data) extends JsonResource
    {
        /** @return array<string, mixed> */
        public function toArray(Request $request): array
        {
            return $this->resource;
        }
    };

    /** @var array{data: array<string, mixed>} $decoded */
    $decoded = json_decode((string) $sent->response(request())->getContent(), true);

    return [...$decoded['data'], 'all_posts' => $allPosts];
}

test('a map over a receiver other than the relation types its element', function () {
    $sent = mappedCollectionsSends();

    expect(publishedResourceKeyTypes(MappedCollectionsResource::class, Post::class))->toBe([
        'collected_spread' => "(Omit<Comment, 'flag'> & { flag: boolean })[]",
        'collected_reads' => '({ content: string; who: string | null })[]',
        'collected_spread_in_author?' => "(Omit<Comment, 'flag'> & { flag: boolean })[]",
        'local_rows' => '({ who: string | null })[]',
        'loaded_rows' => "(Pick<Comment, 'id' | 'content'>)[]",
        'local_proxy' => '{ id: number; content: string }[]',
        'reassigned_parameter' => 'string[]',
        'keyed_rows' => '{ id: number }[] | Record<string, { id: number }>',
        'sorted_ids' => 'number[] | Record<string, number>',
        'sorted_listed_ids' => 'number[]',
        'sorted_first' => 'string | null',
        'loaded_values' => '{ id: number }[]',
        'mapped_values' => '{ id: number }[]',
        'query_rows' => '{ id: number }[]',
        'declared_first' => 'string | null',
        'all_posts' => 'PostResource[]',
        'post_rows' => '{ id: number }[]',
        'query_service_rows' => '{ id: number }[]',
        'unflagged_rows' => '{ id: number }[]',
    ])
        ->and($sent['collected_spread'][0])->toMatchArray(['id' => 1, 'content' => 'alpha', 'flag' => true])
        ->and($sent['collected_reads'])->toBe([['content' => 'alpha', 'who' => 'Ada'], ['content' => 'beta', 'who' => 'Ada']])
        ->and($sent['collected_spread_in_author'][1])->toMatchArray(['id' => 2, 'content' => 'beta', 'flag' => true])
        ->and($sent['local_rows'])->toBe([['who' => 'Ada'], ['who' => 'Ada']])
        ->and($sent['loaded_rows'])->toBe([['id' => 1, 'content' => 'alpha'], ['id' => 2, 'content' => 'beta']])
        ->and($sent['local_proxy'])->toBe([['id' => 1, 'content' => 'alpha'], ['id' => 2, 'content' => 'beta']])
        ->and($sent['reassigned_parameter'])->toBe(['Ada', 'Ada'])
        ->and($sent['keyed_rows'])->toBe(['alpha' => ['id' => 1], 'beta' => ['id' => 2]])
        ->and($sent['sorted_ids'])->toBe([1, 2])
        ->and($sent['sorted_listed_ids'])->toBe([1, 2])
        ->and($sent['sorted_first'])->toBe('alpha')
        ->and($sent['loaded_values'])->toBe([['id' => 1], ['id' => 2]])
        ->and($sent['mapped_values'])->toBe([['id' => 1], ['id' => 2]])
        ->and($sent['query_rows'])->toBe([['id' => 1], ['id' => 2]])
        ->and($sent['declared_first'])->toBe('alpha')
        ->and($sent['all_posts'])->toBeInstanceOf(PostCollection::class)
        ->and($sent['all_posts']->collection->all())->each->toBeInstanceOf(PostResource::class)
        ->and($sent['post_rows'])->toBe([['id' => 1], ['id' => 2]])
        ->and($sent['query_service_rows'])->toBe([['id' => 1], ['id' => 2]])
        ->and($sent['unflagged_rows'])->toBe([['id' => 1], ['id' => 2]]);
});
