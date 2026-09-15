<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\MethodChainHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ReceiverMethodCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverMethodReturnResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverMethodProbe;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverProbeEnum;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverShapedToArrayModel;
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
