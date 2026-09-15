<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\MethodChainHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\PropertyChainHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ReceiverMethodCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ReceiverPropertyFetchHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverMethodReturnResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverIntegerKeyModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverMethodProbe;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverProbeEnum;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverProbeResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverShapedToArrayModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverVarProbe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use Workbench\App\Enums\Priority;
use Workbench\App\Http\Resources\ImageResource;
use Workbench\App\Http\Resources\ReceiverMethodResource;
use Workbench\App\Http\Resources\ReceiverPropertyResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/** Parse one expression statement written with fully-qualified names. */
function receiverHandlerExpr(string $php): Expr
{
    return new AstParser()->parseSource('<?php '.$php.';')[0]->expr;
}

/** A scope whose subject is the method probe, backed by Post. */
function receiverProbeScope(): AnalysisScope
{
    return new AnalysisScope(new ReflectionClass(ReceiverMethodProbe::class), Post::class);
}

/**
 * ReceiverPropertyResource's published property types, keyed by property name.
 *
 * @return array<string, string>
 */
function receiverPropertyTypes(): array
{
    return collect(new ResourceAstAnalyzer(new ReflectionClass(ReceiverPropertyResource::class), Comment::class)->analyze()->properties)
        ->mapWithKeys(fn (array $p): array => [$p['name'] => $p['type']])
        ->all();
}

/** A scope whose subject is the property resource, with `$post` bound the way its body binds it. */
function receiverPropertyScope(): AnalysisScope
{
    $scope = new AnalysisScope(new ReflectionClass(ReceiverPropertyResource::class), Comment::class);
    $scope->localVarBindings['post'] = receiverHandlerExpr('$this->post');

    return $scope;
}

describe('ReceiverMethodCallHandler through the resource analyzer', function () {
    test('follows method return types through every receiver kind', function () {
        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(ReceiverMethodResource::class), Post::class)->analyze()->properties)
            ->mapWithKeys(fn (array $p): array => [$p['name'] => $p['type']]);

        expect($props->all())->toMatchArray([
            'priority_label' => 'string',
            'priority_label_nullsafe' => 'string | null',
            'resource_priority_label' => 'string',
            'resource_priority_label_nullsafe' => 'string | null',
            'resource_published_date' => 'string',
            'published_date' => 'string',
            'author_morph' => 'string | null',
            'record_class' => 'string',
            'from_label' => 'string',
            'author_fresh' => 'User | null',
            'author_fresh_nullsafe' => 'User | null',
            'resource_author_fresh' => 'User | null',
            'resource_author_fresh_nullsafe' => 'User | null',
        ]);
    });

    test('getKey and modelKeys type from the receiver model key type', function () {
        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(ReceiverMethodResource::class), Post::class)->analyze()->properties)
            ->mapWithKeys(fn (array $p): array => [$p['name'] => $p['type']]);

        expect($props['author_key'])->toBe('number | null')
            ->and($props['comment_ids'])->toBe('number[]')
            ->and($props['resource_comment_ids'])->toBe('number[]')
            ->and($props['resource_author_key'])->toBe('number | null');
    });

    test('getKey and modelKeys type the same through the resource proxy and the model', function (string $php, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverMethodResource::class), Post::class);

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        ['$this->author->getKey()', 'number'],
        ['$this->resource->author->getKey()', 'number'],
        ['$this->author?->getKey()', 'number | null'],
        ['$this->resource?->author?->getKey()', 'number | null'],
        ['$this->resource?->getKey()', 'number | null'],
        ['$this->comments?->modelKeys()', 'number[] | null'],
        ['$this->resource?->comments?->modelKeys()', 'number[] | null'],
    ]);

    test('a bare $this->method() on a resource publishes the type of its $this->resource twin', function () {
        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(ReceiverMethodResource::class), Post::class)->analyze()->properties)
            ->mapWithKeys(fn (array $p): array => [$p['name'] => $p['type']]);

        expect($props['bare_key'])->toBe('number')
            ->and($props['resource_key'])->toBe($props['bare_key'])
            ->and($props['bare_comments_count'])->toBe('number')
            ->and($props['resource_comments_count'])->toBe($props['bare_comments_count']);
    });

    test('a method only the model declares types the same bare, nullsafe on $this, and through $this->resource', function (string $call, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverMethodResource::class), Post::class);

        expect($analyzer->resolve(receiverHandlerExpr('$this->'.$call))['type'])->toBe($type)
            ->and($analyzer->resolve(receiverHandlerExpr('$this?->'.$call))['type'])->toBe($type)
            ->and($analyzer->resolve(receiverHandlerExpr('$this->resource->'.$call))['type'])->toBe($type);
    })->with([
        ['getKey()', 'number'],
        ['commentsCount()', 'number'],
        ['fresh()', 'Post | null'],
        ['replicate()', 'Post'],
    ]);

    test('an integer key type publishes number in both $this->resource getKey() spellings', function () {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), ReceiverIntegerKeyModel::class);

        expect($analyzer->resolve(receiverHandlerExpr('$this->resource->getKey()'))['type'])->toBe('number')
            ->and($analyzer->resolve(receiverHandlerExpr('$this->resource?->getKey()'))['type'])->toBe('number | null');
    });

    test('a DateTime return stays unknown in every spelling, since json_encode() writes it as an object', function (string $php) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverMethodResource::class), Post::class);

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe('unknown');
    })->with([
        '$this->resource->published_at->toDateTime()',
        '$this->published_at?->toDateTime()',
        '$this->resource?->published_at?->toDateTime()',
    ]);
});

describe('ReceiverMethodReturnResolver', function () {
    test('a public method types on any receiver, a protected one only from inside', function () {
        $resolver = resolve(ReceiverMethodReturnResolver::class);
        $probe = ReceiverType::of(ReceiverMethodProbe::class);

        expect($resolver->resolve($probe, 'label', receiverProbeScope())['type'] ?? null)->toBe('string')
            ->and($resolver->resolve($probe, 'secret', receiverProbeScope()))->toBeNull()
            ->and($resolver->resolve($probe, 'secret', receiverProbeScope(), fromInside: true)['type'] ?? null)->toBe('number');
    });

    test('a receiver-returning method keeps the receiver type, with null when the return admits it', function () {
        $resolver = resolve(ReceiverMethodReturnResolver::class);

        expect($resolver->resolve(ReceiverType::of(User::class), 'fresh', receiverProbeScope()))
            ->toBe(['type' => 'User | null', 'optional' => false, 'modelFqcn' => User::class])
            ->and($resolver->resolve(ReceiverType::of(ReceiverProbeEnum::class), 'next', receiverProbeScope()))
            ->toBe(['type' => 'ReceiverProbeEnumType | null', 'optional' => false, 'directEnumFqcn' => ReceiverProbeEnum::class])
            ->and($resolver->resolve(ReceiverType::of(Carbon::class), 'setTimezone', receiverProbeScope())['type'] ?? null)
            ->toBe('string');
    });

    test('a class published as string through __toString declines unless it serializes as one', function () {
        $resolver = resolve(ReceiverMethodReturnResolver::class);
        $probe = ReceiverType::of(ReceiverMethodProbe::class);

        expect($resolver->resolve($probe, 'interval', receiverProbeScope()))->toBeNull()
            ->and($resolver->resolve($probe, 'docblockInterval', receiverProbeScope()))->toBeNull()
            ->and($resolver->resolve(ReceiverType::of(Carbon::class), 'diff', receiverProbeScope()))->toBeNull()
            ->and($resolver->resolve($probe, 'docblockIntervals', receiverProbeScope()))->toBeNull()
            ->and($resolver->resolve($probe, 'text', receiverProbeScope())['type'] ?? null)->toBe('string');
    });

    test('a DateTime that is not JsonSerializable declines, while Carbon keeps its string', function () {
        $resolver = resolve(ReceiverMethodReturnResolver::class);

        expect($resolver->resolve(ReceiverType::of(Carbon::class), 'toDateTime', receiverProbeScope()))->toBeNull()
            ->and($resolver->resolve(ReceiverType::of(ReceiverMethodProbe::class), 'plainDate', receiverProbeScope()))->toBeNull()
            ->and($resolver->resolve(ReceiverType::of(Carbon::class), 'toMutable', receiverProbeScope())['type'] ?? null)->toBe('string');
    });

    test('a framework or abstract model token declines, because no file is published for it', function () {
        $resolver = resolve(ReceiverMethodReturnResolver::class);

        expect($resolver->resolve(ReceiverType::of(User::class), 'resolveRouteBinding', receiverProbeScope()))->toBeNull()
            ->and($resolver->resolve(ReceiverType::of(User::class), 'newPivot', receiverProbeScope()))->toBeNull()
            ->and($resolver->resolve(ReceiverType::of(Model::class), 'fresh', receiverProbeScope()))->toBeNull();
    });

    test('a union types only when every class types the method', function () {
        $resolver = resolve(ReceiverMethodReturnResolver::class);

        expect($resolver->resolve(new ReceiverType([Priority::class, Post::class]), 'label', receiverProbeScope()))->toBeNull()
            ->and($resolver->resolve(new ReceiverType([User::class, Post::class]), 'getMorphClass', receiverProbeScope()))
            ->toBe(['type' => 'string', 'optional' => false]);
    });

    test('a model toArray() declines even when it declares a precise shape', function () {
        $resolver = resolve(ReceiverMethodReturnResolver::class);
        $model = ReceiverType::of(ReceiverShapedToArrayModel::class);

        expect($resolver->resolve($model, 'toArray', receiverProbeScope()))->toBeNull()
            ->and($resolver->resolve($model, 'shape', receiverProbeScope())['type'] ?? null)->toBe('{ id: number }');
    });

    test('a vague array return declines', function () {
        expect(resolve(ReceiverMethodReturnResolver::class)->resolve(ReceiverType::of(Post::class), 'attributesToArray', receiverProbeScope()))
            ->toBeNull();
    });
});

describe('ReceiverMethodCallHandler', function () {
    test('leaves a request receiver to the request rule', function () {
        $scope = receiverProbeScope();
        $scope->requestVarNames['request'] = Request::class;

        expect(new ReceiverMethodCallHandler()->resolve(receiverHandlerExpr('$request->ip()'), $scope, chainHandlersThrowingEngine()))
            ->toBeNull();
    });

    test('forwards a bare $this->method() only on a resource, and only for a method the resource does not declare', function () {
        $resource = new AnalysisScope(new ReflectionClass(ReceiverProbeResource::class), Post::class);
        $plain = new AnalysisScope(new ReflectionClass(ReceiverVarProbe::class), Post::class);
        $handler = new ReceiverMethodCallHandler;

        expect($handler->resolve(receiverHandlerExpr('$this->getKey()'), $resource, chainHandlersThrowingEngine())['type'] ?? null)
            ->toBe('number')
            ->and($handler->resolve(receiverHandlerExpr('$this->urls()'), $resource, chainHandlersThrowingEngine()))->toBeNull()
            ->and($handler->resolve(receiverHandlerExpr('$this->getKey()'), $plain, chainHandlersThrowingEngine()))->toBeNull();
    });

    test('reaches a protected static method only through static::', function () {
        $scope = receiverProbeScope();
        $scope->localVarBindings['probe'] = receiverHandlerExpr('new '.ReceiverMethodProbe::class);
        $handler = new ReceiverMethodCallHandler;

        expect($handler->resolve(receiverHandlerExpr('static::secret()'), $scope, chainHandlersThrowingEngine())['type'] ?? null)
            ->toBe('number')
            ->and($handler->resolve(receiverHandlerExpr('$probe::secret()'), $scope, chainHandlersThrowingEngine()))->toBeNull();
    });

    test('an earlier ?-> in the chain makes the call nullable once', function () {
        $scope = new AnalysisScope(new ReflectionClass(ReceiverMethodResource::class), Post::class);
        $handler = new ReceiverMethodCallHandler;

        expect($handler->resolve(receiverHandlerExpr('$this->resource?->author->getMorphClass()'), $scope, chainHandlersThrowingEngine())['type'] ?? null)
            ->toBe('string | null')
            ->and($handler->resolve(receiverHandlerExpr('$this->resource?->author?->getMorphClass()'), $scope, chainHandlersThrowingEngine())['type'] ?? null)
            ->toBe('string | null');
    });
});

describe('MethodChainHandler no longer reflects on the wrong receiver', function () {
    test('declines a nullsafe call whose last step is an unresolved morphTo', function () {
        $scope = new AnalysisScope(new ReflectionClass(ImageResource::class), Image::class);
        $expr = new NullsafeMethodCall(new PropertyFetch(new Variable('this'), 'imageable'), 'getTable');

        expect(new MethodChainHandler()->resolve($expr, $scope, chainHandlersThrowingEngine()))->toBeNull();
    });

    test('declines a nullsafe call whose last step is an attribute, not a relation', function () {
        $scope = new AnalysisScope(new ReflectionClass(ReceiverMethodResource::class), Post::class);
        $expr = new NullsafeMethodCall(new PropertyFetch(new Variable('this'), 'priority'), 'label');

        expect(new MethodChainHandler()->resolve($expr, $scope, chainHandlersThrowingEngine()))->toBeNull();
    });
});

describe('ReceiverPropertyFetchHandler', function () {
    test('types property chains from a local variable holding a model', function () {
        expect(receiverPropertyTypes())->toMatchArray([
            'post_title' => 'string | null',
            'post_published_at' => 'string | null',
            'post_author_name' => 'string | null',
            'post_title_direct' => 'string',
            'post_title_via_this' => 'string | null',
            'post_title_via_resource' => 'string | null',
        ]);
    });

    test('a chain read through $this->resource publishes the type of its $this twin', function () {
        $props = receiverPropertyTypes();

        expect($props['resource_post_title'])->toBe($props['post_title'])
            ->and($props['resource_post_published_at'])->toBe($props['post_published_at'])
            ->and($props['resource_post_author_name'])->toBe($props['post_author_name'])
            ->and($props['resource_post_title_direct'])->toBe($props['post_title_direct'])
            ->and($props['post_title_via_resource'])->toBe($props['post_title_via_this']);
    });

    test('declines a $this->prop leaf, which belongs to ThisPropertyHandler', function () {
        expect(new ReceiverPropertyFetchHandler()->resolve(receiverHandlerExpr('$this->title'), receiverProbeScope(), chainHandlersThrowingEngine()))
            ->toBeNull();
    });

    test('a reflected property json_encode() writes as an object declines, while one it writes as a string types', function () {
        $handler = new ReceiverPropertyFetchHandler;
        $scope = receiverProbeScope();
        $scope->localVarBindings['probe'] = receiverHandlerExpr('new '.ReceiverVarProbe::class);

        expect(LaravelTsPublish::propertyTypes(new ReflectionClass(ReceiverVarProbe::class), 'plainDate')['type'])->toBe('string')
            ->and($handler->resolve(receiverHandlerExpr('$probe->plainDate'), $scope, chainHandlersThrowingEngine()))->toBeNull()
            ->and($handler->resolve(receiverHandlerExpr('$probe->text'), $scope, chainHandlersThrowingEngine())['type'] ?? null)->toBe('string');
    });

    test('a reflected property naming a model no file is published for declines', function () {
        $handler = new ReceiverPropertyFetchHandler;
        $scope = receiverProbeScope();
        $scope->localVarBindings['probe'] = receiverHandlerExpr('new '.ReceiverVarProbe::class);

        expect(LaravelTsPublish::propertyTypes(new ReflectionClass(ReceiverVarProbe::class), 'anyModel')['classFqcns'])->toBe([Model::class])
            ->and($handler->resolve(receiverHandlerExpr('$probe->anyModel'), $scope, chainHandlersThrowingEngine()))->toBeNull();
    });
});

// The inert half of the ordering inventory's PropertyFetch row: both handlers really claim
// `$this->relation?->attr`, and they answer it identically, which is why their order is free.
// Swapping the two registrations changed no golden line; see docs/components/ast-engine.md.
describe('PropertyChainHandler and ReceiverPropertyFetchHandler are inert against each other', function () {
    test('both claim $this->relation?->attr and answer it the same', function () {
        $expr = receiverHandlerExpr('$this->post?->title');

        $chain = new PropertyChainHandler()->resolve($expr, receiverPropertyScope(), chainHandlersThrowingEngine());
        $receiver = new ReceiverPropertyFetchHandler()->resolve($expr, receiverPropertyScope(), chainHandlersThrowingEngine());

        expect($chain)->toBe(['type' => 'string | null', 'optional' => false])
            ->and($receiver)->toBe($chain);
    });
});

describe('PropertyChainHandler declines an unknown-only chain', function () {
    test('a nullsafe chain rooted at a variable declines for the receiver handler', function () {
        expect(new PropertyChainHandler()->resolve(receiverHandlerExpr('$post?->title'), receiverPropertyScope(), chainHandlersThrowingEngine()))
            ->toBeNull();
    });

    test('a $this->prop->subProp chain it cannot type declines for the receiver handler', function () {
        expect(new PropertyChainHandler()->resolve(receiverHandlerExpr('$this->post->nonexistent_column'), receiverPropertyScope(), chainHandlersThrowingEngine()))
            ->toBeNull();
    });
});
