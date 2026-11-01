<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\DeclaredClasses;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ProxyMediaOrStringResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ArchivingPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AttributedBuilderPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AuthoredPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Builders\PublishedPostBuilder;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\BuiltPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ListingPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\OverriddenBuilderPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ProxyShadowPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ScopedPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\SingleModelStepPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Providers\QueryMacroServiceProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverBaseDto;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverChildDto;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverProbeResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverReturnsProbe;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverVarProbe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name\FullyQualified;
use Workbench\App\Enums\Grade;
use Workbench\App\Enums\Priority;
use Workbench\App\Http\Resources\FluentSelfResource;
use Workbench\App\Http\Resources\NarrowedImageableResource;
use Workbench\App\Http\Resources\TeamSubscriberResource;
use Workbench\App\Models\Category;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Depot;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;
use Workbench\App\Models\Product;
use Workbench\App\Models\Profile;
use Workbench\App\Models\RosterSlot;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;
use Workbench\App\Services\UrlService;
use Workbench\App\ValueObjects\PostStats;
use Workbench\Crm\Models\User as CrmUser;

/** Parse one expression statement written with fully-qualified names. */
function receiverExpr(string $php): Expr
{
    return new AstParser()->parseSource('<?php '.$php.';')[0]->expr;
}

/** A scope over a Post-backed resource. */
function postScope(): AnalysisScope
{
    return new AnalysisScope(new ReflectionClass(ReceiverProbeResource::class), Post::class);
}

describe('ReceiverClassResolver::resolve()', function () {
    test('an enum-cast attribute holds its enum', function () {
        expect(resolve(ReceiverClassResolver::class)->resolve(receiverExpr('$this->priority'), postScope())?->classes)
            ->toBe([Priority::class]);
    });

    test('names the enum an enum case fetch holds', function () {
        expect(resolve(ReceiverClassResolver::class)->resolve(receiverExpr('\Workbench\App\Enums\Grade::Pass'), postScope())?->classes)
            ->toBe([Grade::class]);
    });

    test('declines a class constant that is no enum case, and ::class', function (string $php) {
        expect(resolve(ReceiverClassResolver::class)->resolve(receiverExpr($php), postScope()))->toBeNull();
    })->with([
        'a model constant' => ['\Workbench\App\Models\Post::CREATED_AT'],
        'an enum constant that is no case' => ['\Workbench\App\Enums\Grade::Missing'],
        '::class' => ['\Workbench\App\Enums\Grade::class'],
    ]);

    test('a date-cast attribute holds Carbon, and a static-returning call keeps it', function () {
        $resolver = resolve(ReceiverClassResolver::class);

        expect($resolver->resolve(receiverExpr('$this->published_at'), postScope())?->classes)->toBe([Carbon::class])
            ->and($resolver->resolve(receiverExpr('$this->published_at->setTimezone("UTC")'), postScope())?->classes)->toBe([Carbon::class]);
    });

    test('a to-one relation holds its model; a to-many relation is a collection of it', function () {
        $resolver = resolve(ReceiverClassResolver::class);
        $comments = $resolver->resolve(receiverExpr('$this->comments'), postScope());

        expect($resolver->resolve(receiverExpr('$this->author'), postScope())?->classes)->toBe([User::class])
            ->and($comments?->classes)->toBe([EloquentCollection::class])
            ->and($comments?->elementModel)->toBe(Comment::class);
    });

    test('a relation method call is a relation receiver that remembers its related model', function () {
        $receiver = resolve(ReceiverClassResolver::class)->resolve(receiverExpr('$this->author?->posts()'), postScope());

        expect($receiver?->relatedModel)->toBe(Post::class)
            ->and($receiver?->shortCircuits)->toBeTrue();
    });

    test('static, new and container expressions hold the named class', function () {
        $resolver = resolve(ReceiverClassResolver::class);

        expect($resolver->resolve(receiverExpr('\Workbench\App\Enums\Priority::from(1)'), postScope())?->classes)->toBe([Priority::class])
            ->and($resolver->resolve(receiverExpr('new \Workbench\App\Services\UrlService'), postScope())?->classes)->toBe([UrlService::class])
            ->and($resolver->resolve(receiverExpr('resolve(\Workbench\App\Services\UrlService::class)'), postScope())?->classes)->toBe([UrlService::class])
            ->and($resolver->resolve(receiverExpr('app(\Workbench\App\Services\UrlService::class)'), postScope())?->classes)->toBe([UrlService::class]);
    });

    test('a local variable resolves through its single-write binding; an unbound one declines', function () {
        $scope = postScope();
        $scope->localVarBindings['author'] = receiverExpr('$this->author');

        expect(resolve(ReceiverClassResolver::class)->resolve(receiverExpr('$author'), $scope)?->classes)->toBe([User::class])
            ->and(resolve(ReceiverClassResolver::class)->resolve(receiverExpr('$nobody'), $scope))->toBeNull();
    });

    test('$this->resource holds the backing model', function () {
        expect(resolve(ReceiverClassResolver::class)->resolve(receiverExpr('$this->resource'), postScope())?->classes)->toBe([Post::class]);
    });

    test('$this->x and $this->resource->x resolve to the same receiver', function (string $viaThis, string $viaResource) {
        $resolver = resolve(ReceiverClassResolver::class);
        $expected = $resolver->resolve(receiverExpr($viaThis), postScope());

        expect($expected)->not->toBeNull()
            ->and($resolver->resolve(receiverExpr($viaResource), postScope()))->toEqual($expected);
    })->with([
        'enum cast' => ['$this->priority', '$this->resource->priority'],
        'carbon chain' => ['$this->published_at->setTimezone("UTC")', '$this->resource->published_at->setTimezone("UTC")'],
        'to-one relation' => ['$this->author', '$this->resource->author'],
        'to-many relation' => ['$this->comments', '$this->resource->comments'],
        'nullsafe relation method' => ['$this->author?->posts()', '$this->resource?->author?->posts()'],
        'model-only method' => ['$this->author()', '$this->resource->author()'],
    ]);

    test('a morphTo with a generic holds every target; an unresolved one holds its bound', function () {
        $scope = new AnalysisScope(new ReflectionClass(ReceiverProbeResource::class), Image::class);
        $resolver = resolve(ReceiverClassResolver::class);

        expect($resolver->resolve(receiverExpr('$this->reviewable'), $scope)?->classes)
            ->toBe([CrmUser::class, User::class])
            ->and($resolver->resolve(receiverExpr('$this->imageable'), $scope)?->classes)->toBe([Model::class]);
    });

    test('a subject-declared promoted property holds its declared class', function () {
        expect(resolve(ReceiverClassResolver::class)->resolve(receiverExpr('$this->stats'), postScope())?->classes)
            ->toBe([PostStats::class]);
    });

    test('a subject-declared property answers only the $this spelling', function () {
        expect(resolve(ReceiverClassResolver::class)->resolve(receiverExpr('$this->resource->stats'), postScope()))->toBeNull();
    });

    test('$this->method() prefers the subject, then forwards a model-only relation method', function () {
        $resolver = resolve(ReceiverClassResolver::class);
        $author = $resolver->resolve(receiverExpr('$this->author()'), postScope());

        expect($resolver->resolve(receiverExpr('$this->urls()'), postScope())?->classes)->toBe([UrlService::class])
            ->and($author?->classes)->toBe([BelongsTo::class])
            ->and($author?->relatedModel)->toBe(User::class)
            ->and($author?->shortCircuits)->toBeFalse()
            ->and($resolver->resolve(receiverExpr('$this->publishable()'), postScope()))->toBeNull();
    });

    test('getRelated() on a relation receiver holds the related model', function () {
        $receiver = resolve(ReceiverClassResolver::class)->resolve(receiverExpr('$this->comments()->getRelated()'), postScope());

        expect($receiver?->classes)->toBe([Comment::class]);
    });

    // getRelation() returns what is loaded under the name and throws otherwise, so it holds what the relation does.
    test('getRelation() on a model holds the relation loaded under that name', function () {
        $resolver = resolve(ReceiverClassResolver::class);
        $comments = $resolver->resolve(receiverExpr('$this->resource->getRelation("comments")'), postScope());

        expect($comments?->classes)->toBe([EloquentCollection::class])
            ->and($comments?->elementModel)->toBe(Comment::class)
            ->and($resolver->resolve(receiverExpr('$this->resource->getRelation("author")'), postScope())?->classes)->toBe([User::class])
            ->and($resolver->resolve(receiverExpr('$this->resource->getRelation("title")'), postScope()))->toBeNull()
            ->and($resolver->resolve(receiverExpr('$this->resource->getRelation($name)'), postScope()))->toBeNull();
    });

    test('a relation method on a nested model receiver keeps its native relation class', function () {
        expect(resolve(ReceiverClassResolver::class)->resolve(receiverExpr('$this->author->posts()'), postScope())?->classes)
            ->toBe([HasMany::class]);
    });

    test('$request->user() holds the configured auth model', function () {
        config()->set('auth.providers.users.model', User::class);
        $scope = postScope();
        $scope->requestVarNames['request'] = Request::class;

        expect(resolve(ReceiverClassResolver::class)->resolve(receiverExpr('$request->user()'), $scope)?->classes)
            ->toBe([User::class]);
    });

    test('auth(), Auth and request() hold the default guard\'s user as $request->user() does', function () {
        $holds = fn (string $php): ?array => resolve(ReceiverClassResolver::class)->resolve(receiverExpr($php), postScope())?->classes;

        expect(array_map($holds, [
            'auth()->user()',
            '\\Illuminate\\Support\\Facades\\Auth::user()',
            '\\Auth::user()',
            'request()->user()',
            '\\Illuminate\\Support\\Facades\\Auth::guard()->user()',
            'auth()->guard()->user()',
        ]))->each->toBe([User::class])
            ->and(resolve(ReceiverClassResolver::class)->resolve(receiverExpr('auth()?->user()'), postScope())?->shortCircuits)->toBeTrue();
    });

    test('a named guard, a first-class callable and a class other than the Auth facade hold no signed-in user', function () {
        $holds = fn (string $php): ?array => resolve(ReceiverClassResolver::class)->resolve(receiverExpr($php), postScope())?->classes;

        expect(array_map($holds, [
            "auth('admin')->user()",
            "\\Illuminate\\Support\\Facades\\Auth::guard('admin')->user()",
            "auth()->guard('admin')->user()",
            "request()->user('admin')",
            'auth()->user(...)',
            '\\Illuminate\\Support\\Facades\\Config::guard()->user()',
            '\\Foo\\Auth::user()',
        ]))->each->toBeNull();
    });

    test('the signed-in user holds nothing where the default guard\'s provider names no Eloquent model', function () {
        config()->set('auth.providers.users.model', stdClass::class);

        expect(resolve(ReceiverClassResolver::class)->resolve(receiverExpr('auth()->user()'), postScope()))->toBeNull()
            ->and(resolve(ReceiverClassResolver::class)->resolve(receiverExpr('\\Illuminate\\Support\\Facades\\Auth::user()'), postScope()))
            ->toBeNull();
    });

    test('model and collection variable bindings hold their model and their collection', function () {
        $scope = postScope();
        $scope->varModelBindings['comment'] = Comment::class;
        $scope->varCollectionBindings['comments'] = ['type' => 'Comment[]', 'modelFqcn' => Comment::class];
        $resolver = resolve(ReceiverClassResolver::class);
        $collection = $resolver->resolve(receiverExpr('$comments'), $scope);

        expect($resolver->resolve(receiverExpr('$comment'), $scope)?->classes)->toBe([Comment::class])
            ->and($collection?->classes)->toBe([EloquentCollection::class])
            ->and($collection?->elementModel)->toBe(Comment::class);
    });

    test('a self-referential local binding declines instead of recursing', function () {
        $scope = postScope();
        $scope->localVarBindings['a'] = receiverExpr('$b');
        $scope->localVarBindings['b'] = receiverExpr('$a');

        expect(resolve(ReceiverClassResolver::class)->resolve(receiverExpr('$a'), $scope))->toBeNull()
            ->and($scope->resolvingLocalVars)->toBe([]);
    });

    test('date and collection helpers hold their Laravel classes', function () {
        $resolver = resolve(ReceiverClassResolver::class);

        expect($resolver->resolve(receiverExpr('now()'), postScope())?->classes)->toBe([Carbon::class])
            ->and($resolver->resolve(receiverExpr('today()'), postScope())?->classes)->toBe([Carbon::class])
            ->and($resolver->resolve(receiverExpr('collect([])'), postScope())?->classes)->toBe([Collection::class])
            ->and($resolver->resolve(receiverExpr('now(...)'), postScope()))->toBeNull()
            ->and($resolver->resolve(receiverExpr('strlen("x")'), postScope()))->toBeNull();
    });

    test('self, static and a resolved receiver name the class of a static call', function () {
        $resolver = resolve(ReceiverClassResolver::class);
        $scope = postScope();
        $scope->varModelBindings['post'] = Post::class;

        expect($resolver->resolve(receiverExpr('static::make(1)'), $scope)?->classes)->toBe([ReceiverProbeResource::class])
            ->and($resolver->resolve(receiverExpr('self::make(1)'), $scope)?->classes)->toBe([ReceiverProbeResource::class])
            ->and($resolver->resolve(receiverExpr('$post::query()'), $scope)?->classes)->toBe([Builder::class])
            ->and($resolver->resolve(receiverExpr('$this->resource::query()'), $scope)?->classes)->toBe([Builder::class])
            ->and($resolver->resolve(receiverExpr('\Workbench\App\Models\Post::className()'), $scope))->toBeNull();
    });

    test('a static call a model lacks runs on a new query of it, and a query or relation names the model it builds', function () {
        $resolver = resolve(ReceiverClassResolver::class);
        $model = new AnalysisScope(new ReflectionClass(Post::class));

        expect($resolver->resolve(receiverExpr('\Workbench\App\Models\Post::query()'), postScope()))
            ->toEqual(new ReceiverType([Builder::class], relatedModel: Post::class))
            ->and($resolver->resolve(receiverExpr('static::where("title", "x")'), $model))
            ->toEqual(new ReceiverType([Builder::class], relatedModel: Post::class))
            ->and($resolver->resolve(receiverExpr('static::make()'), $model))->toEqual(new ReceiverType([Post::class], unsaved: true))
            ->and($resolver->resolve(receiverExpr('$this->resource->comments()->make()'), postScope()))
            ->toEqual(new ReceiverType([Comment::class], unsaved: true))
            ->and($resolver->resolve(receiverExpr('$this->resource->comments()->create([])'), postScope()))
            ->toEqual(ReceiverType::of(Comment::class));
    });

    // find() returns a model for one key and a collection for a list, which its docblock spells as a conditional.
    test('a query\'s find() names its model for one literal key and a collection of it for a list', function (string $php, ?ReceiverType $held) {
        expect(resolve(ReceiverClassResolver::class)->resolve(receiverExpr($php), postScope()))->toEqual($held);
    })->with([
        'a static' => ['\Workbench\App\Models\Post::find(1)', ReceiverType::of(Post::class)],
        'a static that throws when it misses' => ['\Workbench\App\Models\Post::findOrFail("1")', ReceiverType::of(Post::class)],
        'a query' => ['\Workbench\App\Models\Post::query()->latest()->find(1)', ReceiverType::of(Post::class)],
        'a relation' => ['$this->resource->comments()->find(1)', ReceiverType::of(Comment::class)],
        'a list of keys' => ['\Workbench\App\Models\Post::find([1, 2])', new ReceiverType([EloquentCollection::class], elementModel: Post::class)],
        'a key no literal spells' => ['\Workbench\App\Models\Post::find($id)', null],
    ]);

    // Builder::__call() hands the query back for a call it runs on the base query, and a scope or a when() callback
    // returns its own return, or the query where that is null; a static the model lacks runs on a new query of it.
    test('a query hands on the query a call Eloquent runs on it returns', function (string $php, ?string $model) {
        $held = resolve(ReceiverClassResolver::class)->resolve(receiverExpr($php), postScope());

        expect($held)->toEqual($model === null ? null : new ReceiverType([Builder::class], relatedModel: $model));
    })->with([
        'a base-query call' => ['\Workbench\App\Models\Post::whereIn("id", [1])', Post::class],
        'base-query calls in a chain' => ['\Workbench\App\Models\Post::query()->orderBy("id")->limit(1)', Post::class],
        'a dynamic where' => ['\Workbench\App\Models\Post::whereTitle("x")', Post::class],
        'a soft-delete call' => ['\Workbench\App\Models\Post::withTrashed()', Post::class],
        'a void scope' => ['\AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ScopedPost::titled("x")', ScopedPost::class],
        'an untyped scope returning its query' => ['\AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ScopedPost::query()->identified()', ScopedPost::class],
        'a scope returning a count' => ['\AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ScopedPost::tallied()', null],
        'a when() arrow function' => ['\Workbench\App\Models\Post::query()->when(true, fn ($query) => $query->latest())', Post::class],
        'a when() closure returning nothing' => ['\Workbench\App\Models\Post::when(true, function ($query) { $query->latest(); })', Post::class],
        'an unless() with a default' => ['\Workbench\App\Models\Post::query()->unless(true, fn ($q) => $q, fn ($q) => $q->oldest())', Post::class],
        'a when() callback returning a count' => ['\Workbench\App\Models\Post::query()->when(true, fn ($query) => $query->count())', null],
        'a when() with no callback' => ['\Workbench\App\Models\Post::query()->when(true)', null],
        'a when() whose callback returns null' => ['\Workbench\App\Models\Post::query()->when(true, fn ($query) => null)', Post::class],
        'a when() with a null callback' => ['\Workbench\App\Models\Post::query()->when(false, null)', Post::class],
        'a call when() with no callback runs conditionally' => ['\Workbench\App\Models\Post::query()->when(true)->latest()', Post::class],
        'a terminal when() with no callback runs conditionally' => ['\Workbench\App\Models\Post::query()->when(true)->get()', null],
        'a scope returning null' => ['\AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ScopedPost::unfiltered()', ScopedPost::class],
        'a scope handing on another model\'s query' => ['\AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ScopedPost::remarks()', Comment::class],
        'a call no query runs' => ['\Workbench\App\Models\Post::query()->notAQueryMethod()', null],
        'a through() of a relation' => ['\Workbench\App\Models\Post::throughComments()', null],
    ]);

    // Model::__callStatic() sends a method the `#[Scope]` attribute marks to query(), whose __call() runs the scope.
    test('an attribute scope called statically hands the query back', function () {
        expect(resolve(ReceiverClassResolver::class)->resolve(
            receiverExpr('\AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ScopedPost::recent()'),
            postScope(),
        ))->toEqual(new ReceiverType([Builder::class], relatedModel: ScopedPost::class));
    })->skip(fn (): bool => ! class_exists('Illuminate\Database\Eloquent\Attributes\Scope'), 'The #[Scope] attribute ships in Laravel 12.4.0.');

    // Model::newEloquentBuilder() builds the class an override of it declares, else the one `$builder` names; a method
    // declared as `Builder` or a parent's `self` hands back the builder its body returns.
    test('a model\'s query is the custom builder it names, whose own methods hand it back', function (string $model) {
        $resolver = resolve(ReceiverClassResolver::class);
        $query = new ReceiverType([PublishedPostBuilder::class], relatedModel: $model);

        expect($resolver->resolve(receiverExpr('\\'.$model.'::query()'), postScope()))->toEqual($query)
            ->and($resolver->resolve(receiverExpr('\\'.$model.'::titled()->latest()'), postScope()))->toEqual($query)
            ->and($resolver->resolve(receiverExpr('\\'.$model.'::published()'), postScope()))->toEqual($query)
            ->and($resolver->resolve(receiverExpr('\\'.$model.'::query()->withContent()'), postScope()))->toEqual($query);
    })->with([[BuiltPost::class], [OverriddenBuilderPost::class]]);

    test('a model\'s query is the custom builder its `#[UseEloquentBuilder]` names', function () {
        expect(resolve(ReceiverClassResolver::class)->resolve(receiverExpr('\\'.AttributedBuilderPost::class.'::query()->titled()'), postScope()))
            ->toEqual(new ReceiverType([PublishedPostBuilder::class], relatedModel: AttributedBuilderPost::class));
    })->skip(fn (): bool => ! class_exists('Illuminate\\Database\\Eloquent\\Attributes\\UseEloquentBuilder'), 'The attribute ships in Laravel 12.19.0.');

    test('a builder macro hands on the query its closure or method returns', function () {
        $macros = new ReflectionProperty(Builder::class, 'macros');
        $saved = $macros->getValue();
        app()->register(QueryMacroServiceProvider::class, force: true);
        $resolver = resolve(ReceiverClassResolver::class);

        try {
            expect($resolver->resolve(receiverExpr('\Workbench\App\Models\Post::query()->newestFirst()'), postScope()))
                ->toEqual(new ReceiverType([Builder::class], relatedModel: Post::class))
                ->and($resolver->resolve(receiverExpr('\Workbench\App\Models\Post::titleLike("x")'), postScope()))
                ->toEqual(new ReceiverType([Builder::class], relatedModel: Post::class))
                ->and($resolver->resolve(receiverExpr('\Workbench\App\Models\Post::query()->tally()'), postScope()))->toBeNull()
                ->and($resolver->resolve(receiverExpr('\Workbench\App\Models\Post::query()->commentQuery()'), postScope()))
                ->toEqual(new ReceiverType([Builder::class], relatedModel: Comment::class))
                ->and($resolver->resolve(receiverExpr('\Workbench\App\Models\Post::commentQueryFrom()'), postScope()))
                ->toEqual(new ReceiverType([Builder::class], relatedModel: Comment::class));
        } finally {
            $macros->setValue(null, $saved);
        }
    });

    // A scope that delegates to a helper the engine cannot read neither proves nor disproves it hands the query back:
    // a receiver read names nothing, and a read presuming such steps keeps the query, as a model-rooted chain read.
    // Relation::__call() forwards a call its builder's __call() answers, and hands itself back where the builder does.
    test('a relation hands itself back for a call its builder runs on the base query', function () {
        expect(resolve(ReceiverClassResolver::class)->resolve(receiverExpr('$this->resource->comments()->whereIn("id", [1])->latest()'), postScope()))
            ->toEqual(new ReceiverType([HasMany::class], relatedModel: Comment::class));
    });

    test('a step whose hand-back is unproven names a query only where steps are presumed', function () {
        $resolver = resolve(ReceiverClassResolver::class);
        $query = new ReceiverType([Builder::class], relatedModel: ScopedPost::class);
        $unproven = receiverExpr('\AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ScopedPost::newestVia()');
        $tallied = receiverExpr('\AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ScopedPost::tallied()');

        expect($resolver->resolve($unproven, postScope()))->toBeNull()
            ->and($resolver->resolvePresumingSteps($unproven, postScope()))->toEqual($query)
            ->and($resolver->resolvePresumingSteps($tallied, postScope()))->toBeNull()
            ->and($resolver->resolve($unproven, postScope()))->toBeNull();
    });

    // A model's own method with no declared return, or one declaring a bare collection, hands on what its body returns.
    test('a model\'s own method names the list or model its body returns', function () {
        $resolver = resolve(ReceiverClassResolver::class);
        $listing = '\AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ListingPost';

        expect($resolver->resolve(receiverExpr($listing.'::popular()'), postScope()))
            ->toEqual(new ReceiverType([EloquentCollection::class], elementModel: ListingPost::class))
            ->and($resolver->resolve(receiverExpr($listing.'::popularTyped()'), postScope()))
            ->toEqual(new ReceiverType([EloquentCollection::class], elementModel: ListingPost::class))
            ->and($resolver->resolve(receiverExpr($listing.'::newest()'), postScope()))->toEqual(ReceiverType::of(ListingPost::class))
            ->and($resolver->resolve(receiverExpr($listing.'::rowsVia()'), postScope()))->toBeNull()
            ->and($resolver->resolvePresumingSteps(receiverExpr($listing.'::rowsVia()'), postScope()))
            ->toEqual(new ReceiverType([EloquentCollection::class], elementModel: ListingPost::class));
    });

    // Arms naming different things prove nothing, so only a read presuming steps keeps the model's own query, as base.
    test('a model\'s own method whose branches return different things names a query only where steps are presumed', function () {
        $resolver = resolve(ReceiverClassResolver::class);
        $either = receiverExpr('\AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ListingPost::either(true)');
        $two = receiverExpr('\AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ListingPost::postsOrComments(true)');
        $query = new ReceiverType([Builder::class], relatedModel: ListingPost::class);

        expect($resolver->resolve($either, postScope()))->toBeNull()
            ->and($resolver->resolve($two, postScope()))->toBeNull()
            ->and($resolver->resolvePresumingSteps($either, postScope()))->toEqual($query)
            ->and($resolver->resolvePresumingSteps($two, postScope()))->toEqual($query);
    });

    // lazy() and cursor() hand on a LazyCollection of the models, which item-keeping steps and same-model merges keep;
    // filter() keeps the keys it skips past.
    test('a lazy list and an item-keeping step name the models they hold', function () {
        $resolver = resolve(ReceiverClassResolver::class);
        $lazy = new ReceiverType([LazyCollection::class], elementModel: Post::class);
        $list = new ReceiverType([EloquentCollection::class], elementModel: Post::class);

        expect($resolver->resolve(receiverExpr('\Workbench\App\Models\Post::query()->lazy()'), postScope()))->toEqual($lazy)
            ->and($resolver->resolve(receiverExpr('\Workbench\App\Models\Post::cursor()->filter(fn ($post) => true)'), postScope()))
            ->toEqual(new ReceiverType([LazyCollection::class], elementModel: Post::class, keyed: true))
            ->and($resolver->resolve(receiverExpr('\Workbench\App\Models\Post::all()->shuffle()'), postScope()))->toEqual($list)
            ->and($resolver->resolve(receiverExpr('\Workbench\App\Models\Post::all()->merge([])'), postScope()))->toEqual($list)
            ->and($resolver->resolve(receiverExpr('\Workbench\App\Models\Post::all()->concat(\Workbench\App\Models\Comment::all())'), postScope()))
            ->toEqual(new ReceiverType([EloquentCollection::class]));
    });

    // Builder::__call() runs a local macro, as a global scope's extend() adds, before it would throw.
    test('a call nothing but a local macro could answer is presumed to hand the query back', function () {
        $resolver = resolve(ReceiverClassResolver::class);
        $archived = receiverExpr('\AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ArchivingPost::withArchived()');

        expect($resolver->resolve($archived, postScope()))->toBeNull()
            ->and($resolver->resolvePresumingSteps($archived, postScope()))
            ->toEqual(new ReceiverType([Builder::class], relatedModel: ArchivingPost::class));
    });

    test('a static a model declares keeps reading as its declaration', function () {
        expect(resolve(ReceiverClassResolver::class)->resolve(
            receiverExpr('\AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ScopedPost::newest()'),
            postScope(),
        ))->toEqual(ReceiverType::of(ScopedPost::class));
    });

    // forwardDecoratedCallTo() hands the relation back only for the very builder it holds, which `$this` promises and
    // `static` does not: clone() returns a copy, the builder's until Laravel 13.38 gave Relation its own clone().
    // first()'s `TValue|null` is the model BuildsQueries<TModel> binds.
    test('a call a relation forwards to its builder holds what the builder returns, the relation itself for $this', function () {
        $resolver = resolve(ReceiverClassResolver::class);
        $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
        $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);
        Comment::create(['content' => 'alpha', 'post_id' => $post->id, 'user_id' => $user->id]);
        $relation = $post->comments();
        $copy = method_exists(Relation::class, 'clone') ? HasMany::class : Builder::class;

        expect($resolver->resolve(receiverExpr('$this->resource->comments()->where("id", ">", 0)'), postScope()))
            ->toEqual(new ReceiverType([HasMany::class], relatedModel: Comment::class))
            ->and($resolver->resolve(receiverExpr('$this->resource->comments()->clone()'), postScope()))
            ->toEqual(new ReceiverType([$copy], relatedModel: Comment::class))
            ->and($resolver->resolve(receiverExpr('$this->resource->comments()->first()'), postScope()))->toEqual(ReceiverType::of(Comment::class))
            ->and($resolver->resolve(receiverExpr('$this->resource->comments()->firstWhere("id", 1)'), postScope()))
            ->toEqual(ReceiverType::of(Comment::class))
            ->and($resolver->resolve(receiverExpr('\Workbench\App\Models\Post::query()->first()'), postScope()))->toEqual(ReceiverType::of(Post::class))
            ->and($resolver->resolve(receiverExpr('$this->resource->comments()->count()'), postScope()))->toBeNull()
            ->and($resolver->resolve(receiverExpr('$this->resource->comments()->addHasWhere($query, $relation)'), postScope()))->toBeNull()
            ->and($relation->where('id', '>', 0))->toBe($relation)
            ->and($relation->clone())->toBeInstanceOf($copy)
            ->and($relation->first())->toBeInstanceOf(Comment::class)
            ->and($relation->firstWhere('id', 0))->toBeNull();
    });

    // A `new` with no constructor argument holds no attributes; a copy, a filled model and any model a step reaches
    // may hold some. The binder's `new` for a passed copy is one of those.
    test('marks only a new model built with no constructor argument as holding no attributes', function () {
        $resolver = resolve(ReceiverClassResolver::class);
        $model = new AnalysisScope(new ReflectionClass(Post::class));
        $copy = new New_(new FullyQualified(Post::class), [], [ReceiverClassResolver::HOLDS_ATTRIBUTES => true]);
        $attributeless = fn (string $php, ?AnalysisScope $scope = null): ?bool => $resolver->resolve(receiverExpr($php), $scope ?? postScope())?->attributeless;

        expect($attributeless('new \Workbench\App\Models\Post'))->toBeTrue()
            ->and($attributeless('new static', $model))->toBeTrue()
            ->and($attributeless('new self()', $model))->toBeTrue()
            ->and($attributeless('$flag ? new \Workbench\App\Models\Post : $this->resource'))->toBeTrue()
            ->and($attributeless('new \Workbench\App\Models\Post(["title" => "x"])'))->toBeFalse()
            ->and($attributeless('new \Workbench\App\Services\UrlService'))->toBeFalse()
            ->and($attributeless('$this->resource->replicate()'))->toBeFalse()
            ->and($attributeless('(new \Workbench\App\Models\Post)->fill([])'))->toBeFalse()
            ->and($attributeless('(new \Workbench\App\Models\Post)->author'))->toBeFalse()
            ->and($attributeless('\Workbench\App\Models\Post::query()->firstOrNew([])'))->toBeFalse()
            ->and($resolver->resolve($copy, postScope()))->toEqual(new ReceiverType([Post::class], unsaved: true));
    });

    test('a static-syntax call the subject lacks reads as $this->m(), as __call() runs it', function () {
        $resolver = resolve(ReceiverClassResolver::class);

        expect($resolver->resolve(receiverExpr('static::fresh()'), postScope()))
            ->toEqual($resolver->resolve(receiverExpr('$this->fresh()'), postScope()))
            ->and($resolver->resolve(receiverExpr('static::fresh()'), postScope())?->classes)->toBe([Post::class])
            ->and($resolver->resolve(receiverExpr('$this::fresh()'), postScope())?->classes)->toBe([Post::class]);
    });

    test('a ternary or coalesce holds every non-null arm, and declines on any unresolved arm', function () {
        $resolver = resolve(ReceiverClassResolver::class);

        expect($resolver->resolve(receiverExpr('$flag ? $this->author : null'), postScope())?->classes)->toBe([User::class])
            ->and($resolver->resolve(receiverExpr('$this->author ?: new \Workbench\App\Services\UrlService'), postScope())?->classes)
            ->toBe([User::class, UrlService::class])
            ->and($resolver->resolve(receiverExpr('$this->author ?? $this->author'), postScope())?->classes)->toBe([User::class])
            ->and($resolver->resolve(receiverExpr('$this->author ?? $nobody'), postScope()))->toBeNull()
            ->and($resolver->resolve(receiverExpr('$flag ? null : null'), postScope()))->toBeNull();
    });

    test('a ternary keeps an arm\'s short circuit; a coalesce absorbs its left arm\'s', function () {
        $resolver = resolve(ReceiverClassResolver::class);

        expect($resolver->resolve(receiverExpr('$flag ? $this->author?->posts() : $this->author->posts()'), postScope())?->shortCircuits)->toBeTrue()
            ->and($resolver->resolve(receiverExpr('$this->author?->posts() ?? $this->author->posts()'), postScope())?->shortCircuits)->toBeFalse();
    });

    test('an unsupported expression declines', function () {
        expect(resolve(ReceiverClassResolver::class)->resolve(receiverExpr('"literal"'), postScope()))->toBeNull()
            ->and(resolve(ReceiverClassResolver::class)->resolve(receiverExpr('$this->{$name}'), postScope()))->toBeNull()
            ->and(resolve(ReceiverClassResolver::class)->resolve(receiverExpr('new \NoSuch\Klass'), postScope()))->toBeNull();
    });
});

/** A scope over Image, whose `imageable` morphTo holds Post, Product, User or CRM User once the morph map is seeded. */
function narrowedImageableScope(): AnalysisScope
{
    resolve(ModelAttributeResolver::class)->buildMorphTargetMap([Image::class, Post::class, Product::class, User::class, CrmUser::class]);

    return new AnalysisScope(new ReflectionClass(NarrowedImageableResource::class), Image::class);
}

describe('ReceiverClassResolver instanceof ternary narrowing', function () {
    test('an instanceof test, or an || chain of them, on the true arm\'s own expression holds the tested classes', function () {
        $resolver = resolve(ReceiverClassResolver::class);
        $either = $resolver->resolve(receiverExpr('$this->imageable instanceof \Workbench\App\Models\Post || $this->imageable instanceof \Workbench\App\Models\User ? $this->imageable : null'), narrowedImageableScope());

        expect($either?->classes)->toBe([Post::class, User::class])
            ->and($either?->shortCircuits)->toBeFalse()
            ->and($resolver->resolve(receiverExpr('$this->imageable instanceof \Workbench\App\Models\Post ? $this->imageable : null'), narrowedImageableScope())?->classes)
            ->toBe([Post::class])
            ->and($resolver->resolve(receiverExpr('$flag ? $this->imageable : null'), narrowedImageableScope())?->classes)
            ->toBe([Post::class, Product::class, User::class, CrmUser::class]);
    });

    test('a variable subject narrows though no binding names it, and the narrowed arm still unions with the false arm', function () {
        $resolver = resolve(ReceiverClassResolver::class);

        expect($resolver->resolve(receiverExpr('$record instanceof \Workbench\App\Models\Post ? $record : $this->imageable'), narrowedImageableScope())?->classes)
            ->toBe([Post::class, Product::class, User::class, CrmUser::class])
            ->and($resolver->resolve(receiverExpr('$record instanceof \Workbench\App\Models\Post ? $record : null'), narrowedImageableScope())?->classes)
            ->toBe([Post::class]);
    });

    test('a test narrows what the arm resolves to class by class; a supertype, an interface or a disjoint test never widens it', function () {
        $resolver = resolve(ReceiverClassResolver::class);
        $unmappedImage = new AnalysisScope(new ReflectionClass(ReceiverProbeResource::class), Image::class);
        $comments = $resolver->resolve(receiverExpr('$this->comments instanceof \Illuminate\Database\Eloquent\Collection ? $this->comments : null'), postScope());

        expect($resolver->resolve(receiverExpr('$this->author instanceof \Illuminate\Database\Eloquent\Model ? $this->author : null'), postScope())?->classes)
            ->toBe([User::class])
            ->and($resolver->resolve(receiverExpr('$this->imageable instanceof \Workbench\App\Models\Post ? $this->imageable : null'), $unmappedImage)?->classes)
            ->toBe([Post::class])
            ->and($comments?->classes)->toBe([EloquentCollection::class])
            ->and($comments?->elementModel)->toBe(Comment::class)
            ->and($resolver->resolve(receiverExpr('$this->imageable instanceof \Illuminate\Contracts\Auth\Authenticatable ? $this->imageable : null'), narrowedImageableScope())?->classes)
            ->toBe([Post::class, Product::class, User::class, CrmUser::class])
            ->and($resolver->resolve(receiverExpr('$this->author instanceof \Workbench\App\Models\Post ? $this->author : null'), postScope())?->classes)
            ->toBe([User::class]);
    });

    test('a subclass test beside an interface test keeps the class, in either order, since a subclass may pass the interface', function () {
        $resolver = resolve(ReceiverClassResolver::class);
        $unmappedImage = new AnalysisScope(new ReflectionClass(ReceiverProbeResource::class), Image::class);

        expect($resolver->resolve(receiverExpr('$this->imageable instanceof \Workbench\App\Models\Post || $this->imageable instanceof \Illuminate\Contracts\Auth\Authenticatable ? $this->imageable : null'), $unmappedImage)?->classes)
            ->toBe([Model::class])
            ->and($resolver->resolve(receiverExpr('$this->imageable instanceof \Illuminate\Contracts\Auth\Authenticatable || $this->imageable instanceof \Workbench\App\Models\Post ? $this->imageable : null'), $unmappedImage)?->classes)
            ->toBe([Model::class]);
    });

    test('a narrowed true arm keeps the related model the value it resolved carries', function () {
        $scope = postScope();
        $scope->localVarBindings['relation'] = receiverExpr('$this->comments()');
        $narrowed = resolve(ReceiverClassResolver::class)
            ->resolve(receiverExpr('$relation instanceof \Illuminate\Database\Eloquent\Relations\Relation ? $relation : null'), $scope);

        expect($narrowed?->classes)->toBe([HasMany::class])
            ->and($narrowed?->relatedModel)->toBe(Comment::class);
    });

    test('a narrowed true arm keeps its short circuit, which stands in for the null arm a read on it can still reach', function () {
        $narrowed = resolve(ReceiverClassResolver::class)
            ->resolve(receiverExpr('$this->author?->profile instanceof \Workbench\App\Models\Profile ? $this->author?->profile : null'), postScope());

        expect($narrowed?->classes)->toBe([Profile::class])
            ->and($narrowed?->shortCircuits)->toBeTrue();
    });

    test('each limit the rule states leaves the true arm un-narrowed', function (string $php) {
        expect(resolve(ReceiverClassResolver::class)->resolve(receiverExpr($php), narrowedImageableScope())?->classes)
            ->toBe([Post::class, Product::class, User::class, CrmUser::class]);
    })->with([
        'different subject' => '$record instanceof \Workbench\App\Models\Post ? $this->imageable : null',
        'different property' => '$this->reviewable instanceof \Workbench\App\Models\User ? $this->imageable : null',
        'an || operand on another subject' => '$this->imageable instanceof \Workbench\App\Models\Post || $this->reviewable instanceof \Workbench\App\Models\User ? $this->imageable : null',
        'and' => '$this->imageable instanceof \Workbench\App\Models\Post && $flag ? $this->imageable : null',
        '&& of two tests on the arm' => '$this->imageable instanceof \Workbench\App\Models\Post && $this->imageable instanceof \Workbench\App\Models\User ? $this->imageable : null',
        'negation' => '! $this->imageable instanceof \Workbench\App\Models\Post ? $this->imageable : null',
        'non-instanceof operand' => '$this->imageable instanceof \Workbench\App\Models\Post || $flag ? $this->imageable : null',
        'unloadable class' => '$this->imageable instanceof \NoSuch\Klass ? $this->imageable : null',
        'an unloadable class in an || chain' => '$this->imageable instanceof \Workbench\App\Models\Post || $this->imageable instanceof \NoSuch\Klass ? $this->imageable : null',
        'false-arm read' => '$this->imageable instanceof \Workbench\App\Models\Post ? null : $this->imageable',
        'same value, other spelling' => '$this->resource->imageable instanceof \Workbench\App\Models\Post ? $this->imageable : null',
        '?-> in the test, -> in the arm' => '$this->resource?->imageable instanceof \Workbench\App\Models\Post ? $this->resource->imageable : null',
    ]);

    test('a member read through the tested $this->resource resolves with it narrowed, in the arm the test proves', function (string $php, ?array $classes) {
        $scope = new AnalysisScope(new ReflectionClass(TeamSubscriberResource::class), Team::class);

        expect(resolve(ReceiverClassResolver::class)->resolve(receiverExpr($php), $scope)?->classes)->toBe($classes);
    })->with([
        'the true arm of a test' => ['$this->resource instanceof \Workbench\App\Models\SubscribedTeam ? $this->resource->subscriber : null', [User::class]],
        'the false arm of a negated test' => ['! $this->resource instanceof \Workbench\App\Models\SubscribedTeam ? null : $this->resource->subscriber', [User::class]],
        'a nullsafe chain on the true arm' => ['$this->resource instanceof \Workbench\App\Models\SubscribedTeam ? $this->resource->subscriber?->profile : null', [Profile::class]],
    ]);

    test('a member read in the arm the test does not prove is not narrowed', function (string $php) {
        $scope = new AnalysisScope(new ReflectionClass(TeamSubscriberResource::class), Team::class);
        $proven = '$this->resource instanceof \Workbench\App\Models\SubscribedTeam ? $this->resource->subscriber : null';

        expect(resolve(ReceiverClassResolver::class)->resolve(receiverExpr($php), $scope))->toBeNull()
            ->and(resolve(ReceiverClassResolver::class)->resolve(receiverExpr($proven), $scope)?->classes)->toBe([User::class]);
    })->with([
        'the false arm of a test' => '$this->resource instanceof \Workbench\App\Models\SubscribedTeam ? null : $this->resource->subscriber',
        'the true arm of a negated test' => '! $this->resource instanceof \Workbench\App\Models\SubscribedTeam ? $this->resource->subscriber : null',
        'a test on another subject' => '$this->owner instanceof \Workbench\App\Models\User ? $this->resource->subscriber : null',
    ]);

    test('a member read through a tested variable narrows it, through an || chain and its negation, and never widens it', function () {
        $resolver = resolve(ReceiverClassResolver::class);
        $imageScope = narrowedImageableScope();
        $imageScope->localVarBindings['record'] = receiverExpr('$this->imageable');
        $either = $resolver->resolve(receiverExpr('$record instanceof \Workbench\App\Models\Post || $record instanceof \Workbench\App\Models\User ? $record->comments : null'), $imageScope);
        $negated = $resolver->resolve(receiverExpr('! ($record instanceof \Workbench\App\Models\Post || $record instanceof \Workbench\App\Models\User) ? null : $record->comments'), $imageScope);
        $postScope = postScope();
        $postScope->localVarBindings['author'] = receiverExpr('$this->author');

        expect($either?->classes)->toBe([EloquentCollection::class])
            ->and($either?->elementModel)->toBe(Comment::class)
            ->and($negated?->classes)->toBe([EloquentCollection::class])
            ->and($resolver->resolve(receiverExpr('$record->comments'), $imageScope))->toBeNull()
            ->and($resolver->resolve(receiverExpr('$author instanceof \Illuminate\Database\Eloquent\Model ? $author->profile : null'), $postScope)?->classes)
            ->toBe([Profile::class]);
    });

    test('a method call subject is not narrowed, since a second call need not return the same value', function () {
        $scope = new AnalysisScope(new ReflectionClass(ReceiverReturnsProbe::class));

        expect(resolve(ReceiverClassResolver::class)->resolve(receiverExpr('$this->classUnion() instanceof \Workbench\App\Models\Post ? $this->classUnion() : null'), $scope)?->classes)
            ->toBe([UrlService::class, Post::class]);
    });

    // Only a class other than a model that the redeclaration names keeps the model from answering a forwarded call.
    test('a proven class lacking a forwarded method hands it on to the backing model, which a redeclared class keeps out', function () {
        $resolver = resolve(ReceiverClassResolver::class);
        $proven = postScope();
        $proven->provenProxy = [UrlService::class];

        expect($resolver->proxyCallTarget('fresh', $proven))->toBe(Post::class)
            ->and($resolver->proxyCallTarget('commentsCount', new AnalysisScope(new ReflectionClass(ProxyMediaOrStringResource::class), Post::class)))
            ->toBeNull();
    });
});

describe('ReceiverClassResolver visibility, @var types and class names', function () {
    test('a @var type is read in full: a union with a builtin arm declines, a class or a class-or-null holds the class', function () {
        $scope = new AnalysisScope(new ReflectionClass(ReceiverVarProbe::class));
        $resolver = resolve(ReceiverClassResolver::class);

        expect($resolver->resolve(receiverExpr('$this->collectionOrString'), $scope))->toBeNull()
            ->and($resolver->resolve(receiverExpr('$this->spacedUnion'), $scope))->toBeNull()
            ->and($resolver->resolve(receiverExpr('$this->service'), $scope)?->classes)->toBe([UrlService::class])
            ->and($resolver->resolve(receiverExpr('$this->maybeService'), $scope)?->classes)->toBe([UrlService::class])
            ->and($resolver->resolve(receiverExpr('$this->collectionArray'), $scope))->toBeNull();
    });

    test('a protected model method counts on $this, and declines through a local-variable receiver or the resource proxy', function () {
        $resolver = resolve(ReceiverClassResolver::class);
        $modelSubject = new AnalysisScope(new ReflectionClass(Post::class), Post::class);
        $scope = postScope();
        $scope->localVarBindings['post'] = receiverExpr('$this->resource');

        expect($resolver->resolve(receiverExpr('$this->titleDisplay()'), $modelSubject)?->classes)->toBe([Attribute::class])
            ->and($resolver->resolve(receiverExpr('$post->titleDisplay()'), $scope))->toBeNull()
            ->and($resolver->resolve(receiverExpr('$this->titleDisplay()'), $scope))->toBeNull()
            ->and($resolver->resolve(receiverExpr('$this->resource->titleDisplay()'), $scope))->toBeNull();
    });

    test('a protected property counts on $this but not on another receiver', function () {
        $resolver = resolve(ReceiverClassResolver::class);
        $scope = postScope();
        $scope->localVarBindings['probe'] = receiverExpr('new \\AbeTwoThree\\LaravelTsPublish\\Tests\\Unit\\Ast\\Fixtures\\ReceiverVarProbe');

        expect($resolver->resolve(receiverExpr('$this->hiddenService'), new AnalysisScope(new ReflectionClass(ReceiverVarProbe::class)))?->classes)
            ->toBe([UrlService::class])
            ->and($resolver->resolve(receiverExpr('$probe->hiddenService'), $scope))->toBeNull()
            ->and($resolver->resolve(receiverExpr('$probe->service'), $scope)?->classes)->toBe([UrlService::class]);
    });

    test('with only a framework parent, new self, new static, new parent and self:: name the subject or that parent', function () {
        $resolver = resolve(ReceiverClassResolver::class);
        $fluent = new AnalysisScope(new ReflectionClass(FluentSelfResource::class));

        expect($resolver->resolve(receiverExpr('new static(1)'), postScope())?->classes)->toBe([ReceiverProbeResource::class])
            ->and($resolver->resolve(receiverExpr('new self(1)'), postScope())?->classes)->toBe([ReceiverProbeResource::class])
            ->and($resolver->resolve(receiverExpr('new parent(1)'), postScope())?->classes)->toBe([JsonResource::class])
            ->and($resolver->resolve(receiverExpr('self::make(1)'), postScope())?->classes)->toBe([ReceiverProbeResource::class])
            ->and($resolver->resolve(receiverExpr('new self($this->resource)'), $fluent)?->classes)->toBe([FluentSelfResource::class]);
    });

    test('with a user-land parent, self and parent name the declaring class and its parent, and static the runtime class', function () {
        $resolver = resolve(ReceiverClassResolver::class);
        $own = new AnalysisScope(new ReflectionClass(ReceiverChildDto::class));
        $inherited = new AnalysisScope(new ReflectionClass(ReceiverChildDto::class));
        $inherited->declaringClass = new ReflectionClass(ReceiverBaseDto::class);

        expect($resolver->resolve(receiverExpr('new self'), $own)?->classes)->toBe([ReceiverChildDto::class])
            ->and($resolver->resolve(receiverExpr('new parent'), $own)?->classes)->toBe([ReceiverBaseDto::class])
            ->and($resolver->resolve(receiverExpr('parent::copy()'), $own)?->classes)->toBe([ReceiverBaseDto::class])
            ->and($resolver->resolve(receiverExpr('new self'), $inherited)?->classes)->toBe([ReceiverBaseDto::class])
            ->and($resolver->resolve(receiverExpr('self::copy()'), $inherited)?->classes)->toBe([ReceiverBaseDto::class])
            ->and($resolver->resolve(receiverExpr('new parent'), $inherited))->toBeNull()
            ->and($resolver->resolve(receiverExpr('new static'), $inherited)?->classes)->toBe([ReceiverChildDto::class])
            ->and($resolver->resolve(receiverExpr('static::fresh()'), $inherited)?->classes)->toBe([ReceiverChildDto::class]);
    });
});

describe('DeclaredClasses::returnClasses()', function () {
    test('self names the declaring class, while static and $this name the class read through', function () {
        $resolver = resolve(ReceiverClassResolver::class);
        $viaChild = receiverExpr('(new \\AbeTwoThree\\LaravelTsPublish\\Tests\\Unit\\Ast\\Fixtures\\ReceiverChildDto)->copy()');

        expect(DeclaredClasses::returnClasses(ReceiverChildDto::class, 'copy'))->toBe([ReceiverBaseDto::class])
            ->and(DeclaredClasses::returnClasses(ReceiverChildDto::class, 'fresh'))->toBe([ReceiverChildDto::class])
            ->and(DeclaredClasses::returnClasses(ReceiverChildDto::class, 'docCopy'))->toBe([ReceiverBaseDto::class])
            ->and(DeclaredClasses::returnClasses(ReceiverChildDto::class, 'docFresh'))->toBe([ReceiverChildDto::class])
            ->and($resolver->resolve($viaChild, postScope())?->classes)->toBe([ReceiverBaseDto::class]);
    });

    test('a native class, static, or class-and-null union return names its classes', function () {
        expect(DeclaredClasses::returnClasses(Carbon::class, 'setTimezone'))->toBe([Carbon::class])
            ->and(DeclaredClasses::returnClasses(ReceiverReturnsProbe::class, 'fluent'))->toBe([ReceiverReturnsProbe::class])
            ->and(DeclaredClasses::returnClasses(ReceiverReturnsProbe::class, 'classUnion'))->toBe([UrlService::class, Post::class]);
    });

    test('a native return with a builtin arm, or a missing method, names nothing', function () {
        expect(DeclaredClasses::returnClasses(ReceiverReturnsProbe::class, 'builtinUnion'))->toBeNull()
            ->and(DeclaredClasses::returnClasses(Post::class, 'publishable'))->toBeNull()
            ->and(DeclaredClasses::returnClasses(Post::class, 'noSuchMethod'))->toBeNull();
    });

    test('an untyped method falls back to its @return docblock', function () {
        expect(DeclaredClasses::returnClasses(ReceiverReturnsProbe::class, 'docblockUnion'))->toBe([UrlService::class, Post::class])
            ->and(DeclaredClasses::returnClasses(ReceiverReturnsProbe::class, 'docblockThis'))->toBe([ReceiverReturnsProbe::class])
            ->and(DeclaredClasses::returnClasses(ReceiverReturnsProbe::class, 'docblockUnresolvable'))->toBeNull()
            ->and(DeclaredClasses::returnClasses(Post::class, 'isFeatured'))->toBeNull()
            ->and(DeclaredClasses::returnClasses(ReceiverReturnsProbe::class, 'docblockGeneric'))->toBe([Collection::class])
            ->and(DeclaredClasses::returnClasses(ReceiverReturnsProbe::class, 'docblockGenericArray'))->toBeNull()
            ->and(DeclaredClasses::returnClasses(ReceiverReturnsProbe::class, 'docblockProseMention'))->toBe([UrlService::class]);
    });
});

describe('DeclaredClasses::docblockArms()', function () {
    test('a type keyword PHP reserves names no class arm, in any case', function (string $type, bool $nullable) {
        $context = new ReflectionClass(ReceiverReturnsProbe::class);

        expect(DeclaredClasses::docblockArms($type, $context, ReceiverReturnsProbe::class, ReceiverReturnsProbe::class))
            ->toBe(['classes' => [], 'named' => false, 'nullable' => $nullable]);
    })->with([
        'bool' => ['bool', false],
        'a nullable string' => ['?string', true],
        'uppercase, beside null' => ['STRING|null', true],
        'capitalized' => ['Bool', false],
        'a generic array' => ['array<int, Post>', false],
        'callable' => ['callable', false],
        'a generic iterable' => ['iterable<int>', false],
        'never' => ['never', false],
        'object or null' => ['object|null', true],
        'true or false' => ['true|false', false],
        'mixed' => ['mixed', false],
        'void' => ['void', false],
        'float or int' => ['float|int', false],
    ]);

    // The keywords need no import map, but a cached output still depends on the file the docblock lives in.
    test('a docblock naming only type keywords reads no import, but records its file', function () {
        LaravelTsPublish::partialMock()->shouldReceive('parseFileUseStatements')->never();
        $context = new ReflectionClass(ReceiverReturnsProbe::class);
        DependencyRecorder::start();

        try {
            $arms = DeclaredClasses::docblockArms('int|string|null', $context, ReceiverReturnsProbe::class, ReceiverReturnsProbe::class);
            $paths = DependencyRecorder::paths();
        } finally {
            DependencyRecorder::stop();
        }

        expect($arms)->toBe(['classes' => [], 'named' => false, 'nullable' => true])
            ->and($paths)->toBe([(string) $context->getFileName()]);
    });

    // PHP allows a class named `resource`, so only a lookup can tell; this file's imports name UrlService and Post.
    test('a class arm beside a keyword resolves through the file\'s imports', function () {
        $context = new ReflectionClass(ReceiverReturnsProbe::class);
        $arms = fn (string $type): array => DeclaredClasses::docblockArms($type, $context, ReceiverReturnsProbe::class, ReceiverReturnsProbe::class);

        expect($arms('UrlService|bool|null'))->toBe(['classes' => [UrlService::class], 'named' => false, 'nullable' => true])
            ->and($arms('?Post'))->toBe(['classes' => [Post::class], 'named' => true, 'nullable' => true])
            ->and($arms('resource'))->toBe(['classes' => [], 'named' => false, 'nullable' => false]);
    });
});

// Model::__get() reads an attribute, a camelCase accessor included, before a relation of the same name.
describe('ReceiverClassResolver::singleModelHeldBy()', function () {
    test('names the one model a member read holds, and nothing for a collection, a scalar or several models', function (string $model, string $name, ?string $held) {
        expect(resolve(ReceiverClassResolver::class)->singleModelHeldBy($model, $name))->toBe($held);
    })->with([
        'a to-one relation' => [Post::class, 'author', User::class],
        'a to-many relation' => [Post::class, 'comments', null],
        'a camelCase accessor typed as one model' => [Post::class, 'latestComment', Comment::class],
        'an untyped getter whose body returns one model' => [AuthoredPost::class, 'lead', CrmUser::class],
        'a column shadowing a relation' => [Depot::class, 'supervisor', null],
        'an old-style accessor shadowing a relation' => [ProxyShadowPost::class, 'author', Category::class],
        'a name only an @property tag types' => [SingleModelStepPost::class, 'ghost', User::class],
        'a morphTo of one model' => [SingleModelStepPost::class, 'subject', User::class],
        'a morphTo of several models' => [RosterSlot::class, 'assignee', null],
        'a morphTo bounded only by Model' => [Image::class, 'imageable', null],
        'no member' => [Post::class, 'not_a_member', null],
    ]);
});

describe('ReceiverType', function () {
    test('models() keeps only the Eloquent model classes', function () {
        $type = new ReceiverType([UrlService::class, User::class, Post::class]);

        expect($type->models())->toBe([User::class, Post::class])
            ->and(ReceiverType::of(User::class)->withShortCircuit(true)->shortCircuits)->toBeTrue()
            ->and(ReceiverType::of(User::class, true)->withShortCircuit(false)->classes)->toBe([User::class])
            ->and(ReceiverType::of(Post::class)->asAttributeless())->toEqual(new ReceiverType([Post::class], unsaved: true, attributeless: true))
            ->and(ReceiverType::of(Post::class)->asAttributeless()->withShortCircuit(true)->attributeless)->toBeTrue();
    });
});
