<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverProbeResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverReturnsProbe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PhpParser\Node\Expr;
use Workbench\App\Enums\Priority;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\Services\UrlService;
use Workbench\Crm\Models\User as CrmUser;

/** Parse one expression statement written with fully-qualified names. */
function receiverExpr(string $php): Expr
{
    return new AstParser()->parseSource('<?php '.$php.';')[0]->expr;
}

function postScope(): AnalysisScope
{
    return new AnalysisScope(new ReflectionClass(ReceiverProbeResource::class), Post::class);
}

describe('ReceiverClassResolver::resolve()', function () {
    test('an enum-cast attribute holds its enum', function () {
        expect(resolve(ReceiverClassResolver::class)->resolve(receiverExpr('$this->priority'), postScope())?->classes)
            ->toBe([Priority::class]);
    });

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
            ->and($resolver->resolve(receiverExpr($viaResource), postScope())?->classes)->toBe($expected?->classes);
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
            ->toBe([UrlService::class]);
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

describe('ReceiverClassResolver::returnClasses()', function () {
    test('a native class, static, or class-and-null union return names its classes', function () {
        $resolver = resolve(ReceiverClassResolver::class);

        expect($resolver->returnClasses(Carbon::class, 'setTimezone'))->toBe([Carbon::class])
            ->and($resolver->returnClasses(ReceiverReturnsProbe::class, 'fluent'))->toBe([ReceiverReturnsProbe::class])
            ->and($resolver->returnClasses(ReceiverReturnsProbe::class, 'classUnion'))->toBe([UrlService::class, Post::class]);
    });

    test('a native return with a builtin arm, or a missing method, names nothing', function () {
        $resolver = resolve(ReceiverClassResolver::class);

        expect($resolver->returnClasses(ReceiverReturnsProbe::class, 'builtinUnion'))->toBeNull()
            ->and($resolver->returnClasses(Post::class, 'publishable'))->toBeNull()
            ->and($resolver->returnClasses(Post::class, 'noSuchMethod'))->toBeNull();
    });

    test('an untyped method falls back to its @return docblock', function () {
        $resolver = resolve(ReceiverClassResolver::class);

        expect($resolver->returnClasses(ReceiverReturnsProbe::class, 'docblockUnion'))->toBe([UrlService::class, Post::class])
            ->and($resolver->returnClasses(ReceiverReturnsProbe::class, 'docblockThis'))->toBe([ReceiverReturnsProbe::class])
            ->and($resolver->returnClasses(ReceiverReturnsProbe::class, 'docblockUnresolvable'))->toBeNull()
            ->and($resolver->returnClasses(Post::class, 'isFeatured'))->toBeNull();
    });
});

describe('ReceiverType', function () {
    test('models() keeps only the Eloquent model classes', function () {
        $type = new ReceiverType([UrlService::class, User::class, Post::class]);

        expect($type->models())->toBe([User::class, Post::class])
            ->and(ReceiverType::of(User::class)->withShortCircuit(true)->shortCircuits)->toBeTrue()
            ->and(ReceiverType::of(User::class, true)->withShortCircuit(false)->classes)->toBe([User::class]);
    });
});
