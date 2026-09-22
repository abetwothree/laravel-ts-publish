<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverType;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
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
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PhpParser\Node\Expr;
use Workbench\App\Enums\Priority;
use Workbench\App\Http\Resources\FluentSelfResource;
use Workbench\App\Http\Resources\NarrowedImageableResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;
use Workbench\App\Models\Product;
use Workbench\App\Models\Profile;
use Workbench\App\Models\User;
use Workbench\App\Services\UrlService;
use Workbench\App\ValueObjects\PostStats;
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

    test('a method call subject is not narrowed, since a second call need not return the same value', function () {
        $scope = new AnalysisScope(new ReflectionClass(ReceiverReturnsProbe::class));

        expect(resolve(ReceiverClassResolver::class)->resolve(receiverExpr('$this->classUnion() instanceof \Workbench\App\Models\Post ? $this->classUnion() : null'), $scope)?->classes)
            ->toBe([UrlService::class, Post::class]);
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

    test('with a user-land parent, self and parent decline because an inherited body is analyzed under the child', function () {
        $resolver = resolve(ReceiverClassResolver::class);
        $scope = new AnalysisScope(new ReflectionClass(ReceiverChildDto::class));

        expect($resolver->resolve(receiverExpr('new self'), $scope))->toBeNull()
            ->and($resolver->resolve(receiverExpr('new parent'), $scope))->toBeNull()
            ->and($resolver->resolve(receiverExpr('self::copy()'), $scope))->toBeNull()
            ->and($resolver->resolve(receiverExpr('parent::copy()'), $scope))->toBeNull()
            ->and($resolver->resolve(receiverExpr('new static'), $scope)?->classes)->toBe([ReceiverChildDto::class])
            ->and($resolver->resolve(receiverExpr('static::fresh()'), $scope)?->classes)->toBe([ReceiverChildDto::class]);
    });
});

describe('ReceiverClassResolver::returnClasses()', function () {
    test('self names the declaring class, while static and $this name the class read through', function () {
        $resolver = resolve(ReceiverClassResolver::class);
        $viaChild = receiverExpr('(new \\AbeTwoThree\\LaravelTsPublish\\Tests\\Unit\\Ast\\Fixtures\\ReceiverChildDto)->copy()');

        expect($resolver->returnClasses(ReceiverChildDto::class, 'copy'))->toBe([ReceiverBaseDto::class])
            ->and($resolver->returnClasses(ReceiverChildDto::class, 'fresh'))->toBe([ReceiverChildDto::class])
            ->and($resolver->returnClasses(ReceiverChildDto::class, 'docCopy'))->toBe([ReceiverBaseDto::class])
            ->and($resolver->returnClasses(ReceiverChildDto::class, 'docFresh'))->toBe([ReceiverChildDto::class])
            ->and($resolver->resolve($viaChild, postScope())?->classes)->toBe([ReceiverBaseDto::class]);
    });

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
            ->and($resolver->returnClasses(Post::class, 'isFeatured'))->toBeNull()
            ->and($resolver->returnClasses(ReceiverReturnsProbe::class, 'docblockGeneric'))->toBe([Collection::class])
            ->and($resolver->returnClasses(ReceiverReturnsProbe::class, 'docblockGenericArray'))->toBeNull()
            ->and($resolver->returnClasses(ReceiverReturnsProbe::class, 'docblockProseMention'))->toBe([UrlService::class]);
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
