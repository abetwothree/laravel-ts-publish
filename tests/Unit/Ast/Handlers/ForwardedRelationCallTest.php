<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Metadata\ModelMetadataAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverMethodReturnResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ForwardedRelationCallResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata\ForwardedRelationCallMetadataProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * A saved post by the given author, with the given comments.
 *
 * @param  list<string>  $contents
 */
function forwardedRelationCallPost(User $user, array $contents): Post
{
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);

    foreach ($contents as $content) {
        Comment::create(['content' => $content, 'post_id' => $post->id, 'user_id' => $user->id]);
    }

    return Post::findOrFail($post->id);
}

/**
 * What Laravel sends for ForwardedRelationCallResource over a post with two comments and a post with none.
 *
 * @return array{commented: array<string, mixed>, uncommented: array<string, mixed>}
 */
function forwardedRelationCallSends(): array
{
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $sent = [];

    foreach (['commented' => ['alpha', 'beta'], 'uncommented' => []] as $label => $contents) {
        $resource = new ForwardedRelationCallResource(forwardedRelationCallPost($user, $contents));

        /** @var array{data: array<string, mixed>} $response */
        $response = json_decode((string) $resource->response(request())->getContent(), true);
        $sent[$label] = $response['data'];
    }

    return $sent;
}

// max() declares `mixed`, so it stays unknown though Laravel sends the largest id, or null over no rows.
it('types a relation call the relation forwards to its query', function () {
    $sent = forwardedRelationCallSends();

    expect(publishedResourceKeyTypes(ForwardedRelationCallResource::class, Post::class))->toBe([
        'count' => 'number',
        'exists' => 'boolean',
        'doesnt_exist' => 'boolean',
        'first_content' => 'string | null',
        'first_where_content' => 'string | null',
        'where_count' => 'number',
        'max_id' => 'unknown',
    ])
        ->and($sent['commented'])->toMatchArray([
            'count' => 2,
            'exists' => true,
            'doesnt_exist' => false,
            'first_content' => 'alpha',
            'first_where_content' => 'beta',
            'where_count' => 2,
        ])
        ->and($sent['commented']['max_id'])->toBeInt()
        ->and($sent['uncommented'])->toBe([
            'count' => 0,
            'exists' => false,
            'doesnt_exist' => true,
            'first_content' => null,
            'first_where_content' => null,
            'where_count' => 0,
            'max_id' => null,
        ]);
});

it('types a narrowed relation count in a metadata provider', function () {
    $provider = new ForwardedRelationCallMetadataProvider;
    $author = User::create(['name' => 'Bo', 'email' => 'bo@example.com', 'password' => 'secret']);

    expect(resolve(ModelMetadataAnalyzer::class)->analyze(ForwardedRelationCallMetadataProvider::class, ['comment_count'])->types)
        ->toBe(['comment_count' => 'number | null'])
        ->and($provider->provide(forwardedRelationCallPost($author, ['alpha', 'beta'])))->toBe(['comment_count' => 2])
        ->and($provider->provide($author))->toBe(['comment_count' => null]);
});

it('names the class whose declaration answers a call a relation or an Eloquent builder forwards', function (string $class, string $method, ?string $answers) {
    expect(resolve(ReceiverMethodReturnResolver::class)->forwardedQueryClass($class, $method))->toBe($answers);
})->with([
    'an aggregate the builder passes through to the base query' => [HasMany::class, 'count', QueryBuilder::class],
    'a builder method' => [HasMany::class, 'where', Builder::class],
    'a method the relation declares' => [HasMany::class, 'make', null],
    'an aggregate on the builder itself' => [Builder::class, 'exists', QueryBuilder::class],
    'a base query method $passthru does not list' => [Builder::class, 'whereIn', null],
    'a method the builder declares' => [Builder::class, 'first', null],
    'a model' => [Post::class, 'count', null],
]);

// Relation::__call() and Builder::__call() run a macro under the name before they forward anything.
it('forwards no call a macro answers', function () {
    $post = forwardedRelationCallPost(User::create(['name' => 'Cy', 'email' => 'cy@example.com', 'password' => 'secret']), ['alpha']);
    $globalMacros = new ReflectionProperty(Builder::class, 'macros');
    $saved = $globalMacros->getValue();

    Relation::macro('count', fn (): string => 'relation macro');
    Builder::macro('average', fn (): string => 'builder macro');

    try {
        expect(resolve(ReceiverMethodReturnResolver::class)->forwardedQueryClass(HasMany::class, 'count'))->toBeNull()
            ->and(resolve(ReceiverMethodReturnResolver::class)->forwardedQueryClass(HasMany::class, 'average'))->toBeNull()
            ->and($post->comments()->count())->toBe('relation macro')
            ->and($post->comments()->average('id'))->toBe('builder macro');
    } finally {
        Relation::flushMacros();
        $globalMacros->setValue(null, $saved);
    }
});

// A protected builder method is beyond the relation's reach, so the call reaches the base query, which throws.
it('forwards no call to a builder method the relation cannot reach', function () {
    $relation = forwardedRelationCallPost(User::create(['name' => 'Di', 'email' => 'di@example.com', 'password' => 'secret']), [])->comments();

    expect(resolve(ReceiverMethodReturnResolver::class)->forwardedQueryClass(HasMany::class, 'addHasWhere'))->toBeNull()
        ->and(fn () => $relation->addHasWhere($relation->getQuery(), $relation, '>=', 1, 'and'))->toThrow(BadMethodCallException::class);
});
