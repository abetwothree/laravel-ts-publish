<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\AttributelessModelReadResource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * Runs a read with the workbench factories guessed, as the application that defines them would.
 *
 * @template T
 *
 * @param  Closure(): T  $read
 * @return T
 */
function withWorkbenchFactories(Closure $read): mixed
{
    $guesser = new ReflectionProperty(Factory::class, 'factoryNameResolver');
    $previous = $guesser->getValue();
    Factory::guessFactoryNamesUsing(fn (string $model): string => 'Workbench\\Database\\Factories\\'.class_basename($model).'Factory');

    try {
        return $read();
    } finally {
        $guesser->setValue(null, $previous);
    }
}

/**
 * What Laravel sends for AttributelessModelReadResource over a saved post titled `First`.
 *
 * @return array<string, mixed>
 */
function attributelessModelReadSends(): array
{
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);
    $resource = new AttributelessModelReadResource(Post::query()->findOrFail($post->id));

    /** @var array{data: array<string, mixed>} $sent */
    $sent = json_decode((string) withWorkbenchFactories(fn () => $resource->response(request())->getContent()), true);

    return $sent['data'];
}

// A new model holds no attributes, so each column reads null; a copy, a factory's make(), firstOrNew(), a filled
// constructor and a new model the body writes or fills hold attributes, and a relation, an accessor and a default
// attribute answer as ever.
it('an attribute read on a model with no attributes carries null; a copy that has attributes does not', function () {
    $sent = attributelessModelReadSends();

    expect(withWorkbenchFactories(fn () => publishedResourceKeyTypes(AttributelessModelReadResource::class, Post::class)))->toBe([
        'new_title' => 'string | null',
        'default_title?' => 'string | null',
        'new_key' => 'number | null',
        'replicated_title' => 'string',
        'factory_name' => 'string',
        'first_or_new_title' => 'string',
        'filled_title' => 'string',
        'local_title' => 'string | null',
        'written_title' => 'string',
        'written_content' => 'string | null',
        'nulled_title' => 'unknown',
        'redacted_title' => 'unknown',
        'offset_title' => 'string',
        'filled_local_title' => 'string',
        'exit_filled_title?' => 'string',
        'exit_written_title?' => 'string',
        'parked_title' => 'string | null',
        'shadowed_title' => 'string | null',
        'ran_title' => 'string',
        'replaced_title' => 'string | null',
        'default_written_title?' => 'string',
        'default_offset_title?' => 'string',
        'new_status' => 'StatusType | null',
        'new_reading_time' => 'string',
        'new_comments' => 'Comment[]',
        'default_attribute_title' => 'string',
    ])
        ->and($sent['factory_name'])->toBeString()->not->toBe('')
        ->and(array_diff_key($sent, ['factory_name' => true]))->toBe([
            'new_title' => null,
            'default_title' => null,
            'new_key' => null,
            'replicated_title' => 'First',
            'first_or_new_title' => 'x',
            'filled_title' => 'x',
            'local_title' => null,
            'written_title' => 'x',
            'written_content' => null,
            'nulled_title' => null,
            'redacted_title' => null,
            'offset_title' => 'w',
            'filled_local_title' => 'y',
            'exit_filled_title' => 'y',
            'exit_written_title' => 'w',
            'parked_title' => null,
            'shadowed_title' => null,
            'ran_title' => 'x',
            'replaced_title' => null,
            'default_written_title' => 'z',
            'default_offset_title' => 'v',
            'new_status' => null,
            'new_reading_time' => 'Quick read',
            'new_comments' => [],
            'default_attribute_title' => 'Untitled',
        ]);
});
