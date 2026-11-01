<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\LoadedRelations;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\LoadedRelationsController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ArrayShapedPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\EagerColumnsPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\HidingCollectionPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ScopedAuthorPost;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\RelationHiddenUser;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\RelationKeyCaseUser;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\RelationVisibilityUser;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Stmt\Expression;
use Workbench\App\Models\Address;
use Workbench\App\Models\Category;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * What `$model->load(...)`, written as PHP that names the fixture posts by their short names, adds to a model.
 *
 * @param  class-string<Model>  $model
 * @return array{type: string, models: list<class-string>}|null
 */
function loadedMembers(string $model, string $call, bool $unsaved = false): ?array
{
    $uses = 'use '.Post::class.'; use '.ScopedAuthorPost::class.'; use '.HidingCollectionPost::class.';';
    $statements = new AstParser()->parseSource('<?php '.$uses.' '.$call.';');
    $statement = end($statements);
    assert($statement instanceof Expression);
    $expr = $statement->expr;
    assert($expr instanceof MethodCall || $expr instanceof NullsafeMethodCall);

    return new LoadedRelations()->members($model, $expr, new AnalysisScope(new ReflectionClass(LoadedRelationsController::class), $model), $unsaved);
}

it('reads each argument spelling Laravel reads', function (string $call, string $type, array $models) {
    expect(loadedMembers(Post::class, $call))->toBe(['type' => $type, 'models' => $models]);
})->with([
    'a name' => ['$post->load("author")', '{ author: User }', [User::class]],
    'several names' => [
        '$post->load("author", "comments")',
        '{ author: User; comments: Comment[] }',
        [User::class, Comment::class],
    ],
    'a list' => [
        '$post->load(["author", "comments"])',
        '{ author: User; comments: Comment[] }',
        [User::class, Comment::class],
    ],
    'the named argument' => ['$post->load(relations: "author")', '{ author: User }', [User::class]],
    'a dotted path' => [
        '$post->load("author.posts")',
        '{ author: User & { posts: Post[] } }',
        [User::class, Post::class],
    ],
    'a nested array' => [
        '$post->load(["author" => ["posts", "comments"]])',
        '{ author: User & { posts: Post[]; comments: Comment[] } }',
        [User::class, Post::class, Comment::class],
    ],
    'a dotted path past a named parent' => [
        '$post->load(["author.posts", "author"])',
        '{ author: User & { posts: Post[] } }',
        [User::class, Post::class],
    ],
    'a to-many path' => [
        '$post->load("comments.post")',
        '{ comments: (Comment & { post: Post })[] }',
        [Comment::class, Post::class],
    ],
    'a nullable to-one with a child' => [
        '$post->load("categoryRel.posts")',
        '{ category_rel: Category & { posts: Post[] } | null }',
        [Category::class, Post::class],
    ],
    'columns' => ['$post->load("author:id,name")', "{ author: Pick<User, 'id' | 'name'> }", [User::class]],
    'every column' => ['$post->load("author:*")', '{ author: User }', [User::class]],
    'columns of a list' => [
        '$post->load("comments:id,content")',
        "{ comments: Pick<Comment, 'id' | 'content'>[] }",
        [Comment::class],
    ],
    'columns under a nested key' => [
        '$post->load(["author:id,name" => ["posts"]])',
        "{ author: Pick<User, 'id' | 'name'> & { posts: Post[] } }",
        [User::class, Post::class],
    ],
]);

it('declines an argument it cannot read, or one Laravel throws on', function (string $call) {
    expect(loadedMembers(Post::class, $call))->toBeNull();
})->with([
    'a variable' => ['$post->load($relations)'],
    'no argument' => ['$post->load()'],
    'an unpacked list' => ['$post->load(...$relations)'],
    'a string beside a variable' => ['$post->load("author", $more)'],
    'a closure beside a string' => ['$post->load("author", fn ($q) => $q)'],
    'another named argument' => ['$post->load(names: "author")'],
    'a list item that is not a string' => ['$post->load([$relation])'],
    'a key read at runtime' => ['$post->load([$relation => fn ($q) => $q])'],
    'a spread in the list' => ['$post->load([...$relations])'],
    'columns in a constrained key' => ['$post->load(["author:id" => fn ($q) => $q])'],
    'an empty path segment' => ['$post->load("author.")'],
    'a list under an int key' => ['$post->load([["author"]])'],
    'no relation it knows' => ['$post->load("nothing")'],
]);

it('leaves out a relation whose payload it cannot admit', function (string $call, ?string $type) {
    expect(loadedMembers(Post::class, $call)['type'] ?? null)->toBe($type);
})->with([
    'an unknown relation beside a known one' => ['$post->load(["nothing", "author"])', '{ author: User }'],
    'a column it does not publish' => ['$post->load(["author:id,nope", "comments"])', '{ comments: Comment[] }'],
    'a column list with spaces' => ['$post->load("author:id, name")', null],
    'a closure that selects' => ['$post->load(["author" => fn ($q) => $q->select("id")])', null],
    'a closure calling a scope' => ['$post->load(["author" => fn ($q) => $q->verified()])', null],
    'a closure doing more than a chain' => [
        '$post->load(["comments" => function ($q) { if ($x) { $q->latest(); } }])',
        null,
    ],
    'a closure on another variable' => ['$post->load(["comments" => fn ($q) => $other->latest()])', null],
    'a constraint in a variable' => ['$post->load(["author" => $constraint, "comments"])', '{ comments: Comment[] }'],
    'two constraints on one relation' => ['$post->load(["author:id", "author" => fn ($q) => $q->latest()])', null],
    'a morphTo with a child' => ['$post->load(["images.imageable.author"])', '{ images: Image[] }'],
]);

it('admits null for a to-one relation that may load none', function (string $call, string $type) {
    expect(loadedMembers(Post::class, $call)['type'] ?? null)->toBe($type);
})->with([
    'a closure that orders and counts' => [
        '$post->load(["author" => fn ($q) => $q->latest()->withCount("posts")])',
        '{ author: User }',
    ],
    'a closure that filters' => [
        '$post->load(["author" => fn ($q) => $q->where("name", "x")])',
        '{ author: User | null }',
    ],
    'a closure statement that filters' => [
        '$post->load(["author" => function ($q) { $q->whereKey(1); }])',
        '{ author: User | null }',
    ],
    'a closure that limits' => ['$post->load(["author" => fn ($q) => $q->limit(1)])', '{ author: User | null }'],
    'a filtered list' => [
        '$post->load(["comments" => fn ($q) => $q->where("content", "x")->take(2)])',
        '{ comments: Comment[] }',
    ],
    'columns without the owner key' => ['$post->load("author:name")', "{ author: Pick<User, 'name'> | null }"],
]);

it('admits null for a to-one relation of a model no row backs yet', function () {
    expect(loadedMembers(Post::class, '$post->load("author", "comments")', unsaved: true)['type'] ?? null)
        ->toBe('{ author: User | null; comments: Comment[] }');
});

// With nullable relations off no read publishes `| null`, so only the key a column list leaves out adds it.
it('admits null for a has-one whose columns leave out the key its row is matched by', function () {
    config()->set('ts-publish.models.nullable_relations', false);

    expect(loadedMembers(User::class, '$user->load("primaryAddress:id")'))
        ->toBe(['type' => "{ primary_address: Pick<Address, 'id'> | null }", 'models' => [Address::class]])
        ->and(loadedMembers(User::class, '$user->load("primaryAddress:id,user_id")')['type'] ?? null)
        ->toBe("{ primary_address: Pick<Address, 'id' | 'user_id'> }")
        ->and(loadedMembers(User::class, '$user->load("posts:id")')['type'] ?? null)
        ->toBe("{ posts: Pick<Post, 'id'>[] }");
});

// A column list loads only those columns, so a to-one relation under it finds no row where its key is not among them.
it('admits null for a to-one relation whose parent\'s columns leave out its key', function () {
    config()->set('ts-publish.models.nullable_relations', false);

    expect(loadedMembers(Post::class, '$post->load(["comments:id,post_id,content" => ["user"]])')['type'] ?? null)
        ->toBe("{ comments: (Pick<Comment, 'id' | 'post_id' | 'content'> & { user: User | null })[] }")
        ->and(loadedMembers(Post::class, '$post->load("author:id,name", "author.primaryAddress", "author.posts")')['type'] ?? null)
        ->toBe("{ author: Pick<User, 'id' | 'name'> & { primary_address: Address; posts: Post[] } }")
        ->and(loadedMembers(Post::class, '$post->load("author:name,email", "author.primaryAddress", "author.posts")')['type'] ?? null)
        ->toBe("{ author: Pick<User, 'name' | 'email'> & { primary_address: Address | null; posts: Post[] } | null }")
        ->and(loadedMembers(Post::class, '$post->load("author:*", "author.primaryAddress")')['type'] ?? null)
        ->toBe('{ author: User & { primary_address: Address } }');
});

it('leaves out of a Pick the columns the related model never sends', function () {
    expect(loadedMembers(Post::class, '$post->load("author:id,name,password,remember_token")')['type'] ?? null)
        ->toBe("{ author: Pick<User, 'id' | 'name'> }")
        ->and(loadedMembers(Post::class, '$post->load("author:password")')['type'] ?? null)
        ->toBe('{ author: Pick<User, never> | null }');
});

// Builder::__call() runs a scope before the builder's dynamic where, so `whereSlim()` selects columns, not rows.
it('leaves out a relation whose closure calls a scope named like a filter', function () {
    $author = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $author->id]);
    $sent = ScopedAuthorPost::query()->findOrFail($post->id)->load(['slimAuthor' => fn ($q) => $q->whereSlim()])->toArray();

    expect(loadedMembers(ScopedAuthorPost::class, '$post->load(["slimAuthor" => fn ($q) => $q->whereSlim()])'))->toBeNull()
        ->and(loadedMembers(ScopedAuthorPost::class, '$post->load(["slimAuthor" => fn ($q) => $q->whereKey(0)])')['type'] ?? null)
        ->toBe('{ slim_author: SlimScopedUser | null }')
        ->and(array_keys($sent['slim_author']))->toBe(['id', 'name']);
});

it('publishes a morphTo as its read does, and leaves it out under columns or a child', function () {
    expect(loadedMembers(Image::class, '$image->load("reviewable")'))
        ->toBe(['type' => '{ reviewable: User | User | null }', 'models' => [CrmUser::class, User::class]])
        ->and(loadedMembers(Image::class, '$image->load("reviewable:id")'))->toBeNull()
        ->and(loadedMembers(Image::class, '$image->load("reviewable.posts")'))->toBeNull();
});

it('keys a relation as toArray() writes it, and leaves out one it never writes', function () {
    expect(loadedMembers(RelationKeyCaseUser::class, '$user->load("primaryAddress", "ownedTeams")')['type'] ?? null)
        ->toBe('{ primaryAddress: Address | null; ownedTeams: Team[] }')
        ->and(loadedMembers(User::class, '$user->load("primaryAddress", "ownedTeams")')['type'] ?? null)
        ->toBe('{ primary_address: Address | null; owned_teams: Team[] }')
        ->and(loadedMembers(RelationHiddenUser::class, '$user->load("ownedTeams", "posts")')['type'] ?? null)
        ->toBe('{ posts: Post[] }')
        ->and(loadedMembers(RelationVisibilityUser::class, '$user->load("ownedTeams", "posts")')['type'] ?? null)
        ->toBe('{ owned_teams: Team[] }');
});

// loadMissing() leaves a relation `$with` already loaded as it is, so columns it picked stay picked; load() reloads it.
it('leaves out what loadMissing() keeps as `$with` narrowed it', function () {
    $author = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $author->id]);
    $sent = EagerColumnsPost::query()->findOrFail($post->id)->loadMissing('author', 'categoryRel.posts')->toArray();

    expect(loadedMembers(EagerColumnsPost::class, '$post->loadMissing("author")'))->toBeNull()
        ->and(loadedMembers(EagerColumnsPost::class, '$post->loadMissing("author", "categoryRel.posts")')['type'] ?? null)
        ->toBe('{ category_rel: Category | null }')
        ->and(loadedMembers(EagerColumnsPost::class, '$post->load("author", "categoryRel.posts")')['type'] ?? null)
        ->toBe('{ author: User; category_rel: Category & { posts: Post[] } | null }')
        ->and(array_keys($sent['author']))->toBe(['id', 'name']);
});

// An eager load earlier in the chain is already loaded when loadMissing() runs, so the columns it picked stay picked.
it('leaves out what loadMissing() keeps as an earlier load in its chain narrowed it', function (string $call, ?string $type) {
    expect(loadedMembers(Post::class, $call)['type'] ?? null)->toBe($type);
})->with([
    'a query\'s columns' => ['Post::with("author:id")->first()?->loadMissing("author")', null],
    'a load\'s columns' => ['$post->load("author:id")->loadMissing("author", "comments")', '{ comments: Comment[] }'],
    'a nested path\'s columns' => ['Post::with("author.posts:id")->first()->loadMissing("author.posts")', '{ author: User }'],
    'a closure' => ['Post::query()->with(["author" => fn ($q) => $q->select("id")])->first()->loadMissing("author")', null],
    'names held in a variable' => ['Post::with($relations)->first()->loadMissing("author")', null],
    'a plain eager load' => ['Post::with("author")->first()->loadMissing("author")', '{ author: User }'],
    'load() reloads what the chain loaded' => ['Post::with("author:id")->first()->load("author")', '{ author: User }'],
]);

it('sends what an earlier load in the chain picked to loadMissing()', function () {
    $author = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $author->id]);

    expect(array_keys(Post::with('author:id')->first()?->loadMissing('author')->toArray()['author'] ?? []))->toBe(['id']);
});

// A callback, an unread eager load or the application's own code in the chain may have loaded the relation narrowed.
it('keeps the model\'s type where loadMissing() follows a chain step it cannot read', function (string $model, string $call, ?string $type) {
    expect(loadedMembers($model, $call)['type'] ?? null)->toBe($type);
})->with([
    'a callback' => [Post::class, 'Post::query()->when(true, fn ($q) => $q->with("author:id"))->first()?->loadMissing("author")', null],
    'tap()' => [Post::class, 'Post::query()->tap(fn ($q) => $q->with("author:id"))->first()?->loadMissing("author")', null],
    'withWhereHas()' => [Post::class, 'Post::withWhereHas("author:id")->first()?->loadMissing("author")', null],
    'a scope' => [ScopedAuthorPost::class, 'ScopedAuthorPost::query()->withSlimAuthor()->first()?->loadMissing("author")', null],
    'the framework\'s own steps' => [Post::class, 'Post::query()->latest()->whereKey(1)->first()?->loadMissing("author")', '{ author: User }'],
]);

// Only the steps known to leave relations alone are read through; every other one may load, set or hide one unseen.
it('reads a load\'s chain only through the steps it lists', function (string $call, ?string $type) {
    expect(loadedMembers(Post::class, $call)['type'] ?? null)->toBe($type);
})->with([
    'a bare fresh()' => ['Post::query()->firstOrFail()->fresh()?->loadMissing("author")', '{ author: User }'],
    'fresh() with a relation' => ['$post->fresh("author:id")?->loadMissing("author")', null],
    'fresh() with a list' => ['$post->fresh(["author:id"])?->loadMissing("author")', null],
    'setRelation()' => ['$post->setRelation("author", null)->loadMissing("author")', null],
    'refresh()' => ['$post->refresh()->loadMissing("author")', null],
    'withoutRelations()' => ['$post->withoutRelations()->loadMissing("author")', null],
    'append() before load()' => ['$post->append("excerpt")->load("author")', null],
    'a collection step before load()' => ['Post::all()->sortBy("id")->load("author")', null],
    'a fetch on what the analysis cannot name' => ['$service->first()->loadMissing("author")', null],
    'filters, orders and fetches' => [
        'Post::query()->where("id", 1)->orWhereNull("title")->oldest()->take(1)->firstOrFail()->loadMissing("author")',
        '{ author: User }',
    ],
    'a collection\'s fetch' => ['Post::all()->first()?->loadMissing("author")', '{ author: User }'],
]);

// A step the application's own collection or model declares is its code, whatever the step is named like.
it('keeps the model\'s type past a step a custom collection or the model overrides', function (string $model, string $call) {
    expect(loadedMembers($model, $call))->toBeNull();
})->with([
    'a collection\'s own first()' => [HidingCollectionPost::class, 'HidingCollectionPost::all()->first()?->load("author")'],
    'the model\'s own fresh()' => [ScopedAuthorPost::class, 'ScopedAuthorPost::query()->first()?->fresh()?->loadMissing("author")'],
]);

it('sends what an overridden first() hides and an overridden fresh() picked', function () {
    $author = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $author->id]);
    $first = HidingCollectionPost::all()->first()?->load('author')->toArray() ?? [];
    $fresh = ScopedAuthorPost::query()->first()?->fresh()?->loadMissing('author')->toArray() ?? [];

    expect([array_key_exists('author', $first), array_keys($fresh['author'] ?? [])])->toBe([false, ['id']]);
});

it('sends what a scope in the chain eager loaded to loadMissing(), and leaves out what a chain step hides', function () {
    $author = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $author->id]);
    $scoped = ScopedAuthorPost::query()->withSlimAuthor()->first()?->loadMissing('author')->toArray();
    $listed = Post::all()->makeHidden('author')->load('author')->toArray();
    $own = ScopedAuthorPost::query()->firstOrFail()->hideAuthor()->load('author')->toArray();

    expect(array_keys($scoped['author'] ?? []))->toBe(['id'])
        ->and([array_key_exists('author', $listed[0]), array_key_exists('author', $own)])->toBe([false, false]);
});

// Laravel writes no relation a visibility call earlier in the chain hides, so the call's members leave it out.
it('leaves out a relation a visibility call earlier in the chain keeps out of toArray()', function (string $model, string $call, ?string $type) {
    expect(loadedMembers($model, $call)['type'] ?? null)->toBe($type);
})->with([
    'makeHidden()' => [Post::class, '$post->makeHidden("author")->load("author", "comments")', '{ comments: Comment[] }'],
    'makeHidden() of a list' => [Post::class, '$post->makeHidden(["author"])->load("author")', null],
    'makeHiddenIf()' => [Post::class, '$post->makeHiddenIf(true, "author")->load("author", "comments")', '{ comments: Comment[] }'],
    'mergeHidden()' => [Post::class, '$post->mergeHidden(["comments"])->loadMissing("author", "comments")', '{ author: User }'],
    'setVisible()' => [Post::class, '$post->setVisible(["id", "author"])->load("author", "comments")', '{ author: User }'],
    'a collection\'s makeHidden()' => [Post::class, 'Post::all()->makeHidden("author")->load("author")', null],
    'names held in a variable' => [Post::class, '$post->makeHidden($names)->load("author")', null],
    'a callback' => [Post::class, '$posts->each(fn ($p) => $p->makeHidden("author"))->load("author")', null],
    'a method of the model\'s own' => [ScopedAuthorPost::class, '$post->hideAuthor()->load("author")', null],
    'a scope, which hides nothing' => [ScopedAuthorPost::class, 'ScopedAuthorPost::query()->withSlimAuthor()->first()?->load("author")', '{ author: User }'],
]);

it('declines a model that writes its own array', function () {
    expect(loadedMembers(ArrayShapedPost::class, '$post->load("author")'))->toBeNull();
});
