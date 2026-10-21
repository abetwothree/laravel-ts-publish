<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\FilledTransformValueResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * What Laravel sends for FilledTransformValueResource over a saved rated post and a saved unrated one.
 *
 * @return array{rated: array<string, mixed>, unrated: array<string, mixed>}
 */
function filledTransformValueSends(): array
{
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $sent = [];

    foreach (['rated' => '4.50', 'unrated' => null] as $label => $rating) {
        $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id, 'rating' => $rating]);
        $resource = new FilledTransformValueResource(Post::findOrFail($post->id));

        /** @var array{data: array<string, mixed>} $response */
        $response = json_decode((string) $resource->response(request())->getContent(), true);
        $sent[$label] = $response['data'];
    }

    return $sent;
}

// Laravel sends the rating for a rated post and leaves each conditional key out for an unrated one, but the default's
// null; the proven ternary and the guarded helper send `none` instead, and a when() over a null local sends nothing.
it('reads transform()\'s callback parameter without the value\'s null', function () {
    expect(publishedResourceKeyTypes(FilledTransformValueResource::class, Post::class))->toBe([
        'scalar?' => 'string',
        'shape?' => '{ value: string }',
        'callable?' => '{ value: string }',
        'helper?' => 'string',
        'default' => 'string | null',
        'when_proved?' => 'string',
        'only_null?' => 'null',
        'ternary' => 'string',
        'guarded' => 'string',
    ])
        ->and(filledTransformValueSends())->toBe([
            'rated' => [
                'scalar' => '4.50',
                'shape' => ['value' => '4.50'],
                'callable' => ['value' => '4.50'],
                'helper' => '4.50',
                'default' => '4.50',
                'when_proved' => '4.50',
                'ternary' => '4.50',
                'guarded' => '4.50',
            ],
            'unrated' => ['default' => null, 'ternary' => 'none', 'guarded' => 'none'],
        ]);
});
