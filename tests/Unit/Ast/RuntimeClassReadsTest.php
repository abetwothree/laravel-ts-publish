<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BaseHelperLevelResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BasePrivateLevelResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BaseWrittenTierResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BuiltInstanceResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\LateReadsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\NumberTierResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ParameterTierResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SevenHelperLevelResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SevenLevelResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SilverTierResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StaticSyntaxResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\TrueHelperLevelResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\TrueLevelResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Providers\ResourceMacroServiceProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\NinthViewCounter;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\StartedViewCounter;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\StaticSyntaxPayload;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\NinthViewCountingModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverBaseDto;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverChildDto;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverHolderBaseDto;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverHolderChildDto;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ViewCountingModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ViewCountingResource;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt\Expression;
use Workbench\App\Http\Resources\NoticeResource;
use Workbench\App\Models\Post;
use Workbench\App\ValueObjects\PanelPayload;

/** Parse one `$this->m()` call written as a statement. */
function runtimeClassCall(string $php): Expr
{
    $statement = new AstParser()->parseSource('<?php '.$php.';')[0];
    assert($statement instanceof Expression);

    return $statement->expr;
}

/**
 * What StaticSyntaxResource sends over a fresh Post, its inherited `toArray()` run as PHP runs it.
 *
 * @return array<string, mixed>
 */
function staticSyntaxResponse(): array
{
    return (new StaticSyntaxResource(new Post))->resolve(request());
}

it('reads a method an inherited body calls on the runtime class', function () {
    expect(analyzedKeyTypes(PanelPayload::class))->toMatchArray(['title' => 'string', 'child_only' => 'boolean'])
        ->and(analyzedKeyTypes(PanelPayload::class))->not->toHaveKey('base_only')
        ->and(json_encode(new PanelPayload()->toArray()))->toContain('"title":"x","child_only":true');
});

it('walks the with() and the spread helper the runtime class declares', function () {
    expect(analyzedKeyTypes(NoticeResource::class))->toMatchArray([
        'headline' => 'string', 'child_only' => 'boolean', 'meta' => 'number',
    ])->and(analyzedKeyTypes(NoticeResource::class))->not->toHaveKey('base_only')
        ->and((new NoticeResource(null))->resolve(request()))->toMatchArray(['headline' => 'x', 'child_only' => true, 'meta' => 1])
        ->and((new NoticeResource(null))->resolve(request()))->not->toHaveKey('base_only');
});

it('keeps a member the declaring class declares private', function () {
    expect(analyzedKeyTypes(PanelPayload::class)['weight'])->toBe('number')
        ->and(new PanelPayload()->toArray()['weight'])->toBe(1);
});

it('types the receiver an inherited body\'s $this->m() returns as the runtime class runs it', function () {
    $scope = new AnalysisScope(new ReflectionClass(ReceiverBaseDto::class));
    $scope->runtimeReflection = new ReflectionClass(ReceiverChildDto::class);
    $resolver = resolve(ReceiverClassResolver::class);

    expect($resolver->resolve(runtimeClassCall('$this->fresh()'), $scope)?->classes)->toBe([ReceiverChildDto::class])
        ->and($resolver->resolve(runtimeClassCall('$this->copy()'), $scope)?->classes)->toBe([ReceiverBaseDto::class])
        ->and(new ReceiverChildDto()->fresh())->toBeInstanceOf(ReceiverChildDto::class)
        ->and(new ReceiverChildDto()->copy())->not->toBeInstanceOf(ReceiverChildDto::class);
});

it('spreads the helper a `self::` call in an inherited body names, not the runtime class\'s override', function () {
    expect(publishedResourceKeyTypes(StaticSyntaxResource::class, Post::class))->toHaveKey('base_helper', 'number')
        ->not->toHaveKey('child_helper')
        ->and(staticSyntaxResponse())->toHaveKey('base_helper', 1)->not->toHaveKey('child_helper');
});

it('reads a method on the `new self` an inherited body builds, not on the runtime class', function () {
    expect(publishedResourceKeyTypes(StaticSyntaxResource::class, Post::class))->toHaveKey('built', '{ base_label: number }')
        ->and(staticSyntaxResponse()['built'])->toBe(['base_label' => 1]);
});

it('forwards a `self::` call the declaring class lacks to the model, though the runtime class declares it', function () {
    expect(publishedResourceKeyTypes(StaticSyntaxResource::class, Post::class))->toHaveKey('forwarded', 'boolean')
        ->and(staticSyntaxResponse()['forwarded'])->toBeFalse();
});

it('reads a `$this::` call on the runtime class, where no private member of the declaring class answers', function () {
    expect(analyzedKeyTypes(StaticSyntaxPayload::class))->toBe(['static_syntax' => 'string', 'arrow' => 'number'])
        ->and(new StaticSyntaxPayload()->toArray())->toBe(['static_syntax' => 'x', 'arrow' => 1]);
});

it('reads a `static::` call in an inherited body on the runtime class', function () {
    expect(publishedResourceKeyTypes(StaticSyntaxResource::class, Post::class))->toHaveKey('child_late', 'boolean')
        ->toHaveKey('late', 'string')
        ->not->toHaveKey('base_late')
        ->and(staticSyntaxResponse())->toHaveKey('child_late', true)->toHaveKey('late', 'child')->not->toHaveKey('base_late');
});

it('reads static:: and an untyped default in an inherited body as the runtime class', function () {
    expect(analyzedKeyTypes(PanelPayload::class))->toMatchArray([
        'label' => 'number', 'tier' => 'number', 'secret' => 'string', 'late' => 'string',
    ])->and(analyzedKeyTypes(NoticeResource::class))->toMatchArray(['label' => 'number', 'tier' => 'number'])
        ->and(json_encode(new PanelPayload()->toArray()))->toContain('"label":7,"tier":3,"secret":"x"');
});

it('reads static:: and $this->prop in an inherited resource body as the runtime class sends them', function () {
    expect((new NoticeResource(null))->resolve(request()))->toMatchArray(['label' => 7, 'tier' => 3]);
});

it('reads a method on an instance an inherited body builds with $this as the class it builds', function () {
    expect(publishedResourceKeyTypes(BuiltInstanceResource::class, Post::class))->toMatchArray([
        'built' => '{ base_part: number }',
        'late_built' => '{ child_part: boolean }',
        'late_made' => '{ child_part: boolean }',
    ])->and((new BuiltInstanceResource(new Post))->resolve(request()))->toMatchArray([
        'built' => ['base_part' => 1],
        'late_built' => ['child_part' => true],
        'late_made' => ['child_part' => true],
    ]);
});

it('reads a static:: key and a declared property of a model-backed inherited body on the runtime class', function () {
    expect(publishedResourceKeyTypes(LateReadsResource::class, Post::class))
        ->toMatchArray(['child_key' => 'number', 'tier' => 'number', 'secret' => 'string'])
        ->and((new LateReadsResource(new Post))->resolve(request()))->toMatchArray(['child_key' => 1, 'tier' => 3, 'secret' => 'x']);
});

it('reads a property only the runtime class declares, and `new static` or static::make() as a value, on that class', function () {
    $sent = (new LateReadsResource(new Post))->resolve(request());

    expect(publishedResourceKeyTypes(LateReadsResource::class, Post::class))->toMatchArray([
        'badge' => 'number', 'again' => 'LateReadsResource', 'made' => 'LateReadsResource',
    ])->and($sent['badge'])->toBe(5)
        ->and($sent['again'])->toBeInstanceOf(LateReadsResource::class)
        ->and($sent['made'])->toBeInstanceOf(LateReadsResource::class);
});

it('types `new static` and a `$this->prop` receiver in an inherited body as the runtime class declares them', function () {
    $scope = new AnalysisScope(new ReflectionClass(ReceiverHolderBaseDto::class));
    $scope->runtimeReflection = new ReflectionClass(ReceiverHolderChildDto::class);
    $resolver = resolve(ReceiverClassResolver::class);

    expect($resolver->resolve(runtimeClassCall('new static'), $scope)?->classes)->toBe([ReceiverHolderChildDto::class])
        ->and($resolver->resolve(runtimeClassCall('$this->held'), $scope)?->classes)->toBe([ReceiverChildDto::class])
        ->and($resolver->resolve(runtimeClassCall('$this->own'), $scope)?->classes)->toBe([ReceiverBaseDto::class])
        ->and(new ReceiverHolderChildDto()->fresh())->toBeInstanceOf(ReceiverHolderChildDto::class)
        ->and(new ReceiverHolderChildDto()->heldClass())->toBe(ReceiverChildDto::class)
        ->and(new ReceiverHolderChildDto()->ownClass())->toBe(ReceiverBaseDto::class);
});

/**
 * What a BaseWrittenTierResource subclass sends over a fresh Post, its inherited `toArray()` run as PHP runs it.
 *
 * @return array<string, mixed>
 */
function writtenTierResponse(BaseWrittenTierResource $resource): array
{
    return $resource->resolve(request());
}

it('types an untyped property as its default joined with every literal a method the runtime class runs writes', function (
    BaseWrittenTierResource $resource,
    string $type,
    mixed $default,
    Closure $write,
    mixed $written,
) {
    expect(publishedResourceKeyTypes($resource::class, Post::class)['tier'])->toBe($type)
        ->and(writtenTierResponse($resource)['tier'])->toBe($default)
        ->and(writtenTierResponse($write($resource))['tier'])->toBe($written);
})->with([
    'a string literal' => [fn () => new SilverTierResource(new Post), 'string', 'gold', fn (SilverTierResource $r) => $r->promote(), 'silver'],
    'an int literal' => [fn () => new NumberTierResource(new Post), 'string | number', 'gold', fn (NumberTierResource $r) => $r->promote(), 5],
    'a parameter' => [
        fn () => new ParameterTierResource(new Post), 'unknown', 'gold', fn (ParameterTierResource $r) => $r->promote(['x' => 1]), ['x' => 1],
    ],
]);

it('types an untyped property a class writes itself as its default joined with the literal written', function () {
    expect(publishedResourceKeyTypes(BaseWrittenTierResource::class, Post::class))->toBe(['tier' => 'string', 'level' => 'number | string'])
        ->and(writtenTierResponse(new BaseWrittenTierResource(new Post)))->toBe(['tier' => 'gold', 'level' => 1])
        ->and(writtenTierResponse(new BaseWrittenTierResource(new Post)->maxLevel()))->toBe(['tier' => 'gold', 'level' => 'max']);
});

// Siblings read in one process: a `static::` write typed once per class would leak one sibling's type to the next.
it('types a private property\'s `static::` write against each runtime class that reads it', function () {
    $levels = [];

    foreach ([BasePrivateLevelResource::class, SevenLevelResource::class, TrueLevelResource::class] as $resource) {
        $levels[$resource] = [
            publishedResourceKeyTypes($resource, Post::class)['level'],
            (new $resource(new Post))->resolve(request())['level'],
            (new $resource(new Post))->raise()->resolve(request())['level'],
        ];
    }

    expect($levels)->toBe([
        BasePrivateLevelResource::class => ['string', 'x', 'base'],
        SevenLevelResource::class => ['string | number', 'x', 7],
        TrueLevelResource::class => ['string | boolean', 'x', true],
    ]);
});

it('leaves a receiver\'s property `unknown` where a write names `static`, since its object may be a subclass', function () {
    expect(publishedResourceKeyTypes(SevenLevelResource::class, Post::class)['views'])->toBe('unknown')
        ->and((new SevenLevelResource(new Post))->resolve(request())['views'])->toBe(9);
});

// The holder's body only assumes the class the receiver declares; only its direct `static::` read still reads it.
it('declines a `static` write in a body a receiver\'s call reaches, since the object may be a subclass', function () {
    $reads = [];

    foreach ([BasePrivateLevelResource::class, SevenLevelResource::class, TrueLevelResource::class] as $resource) {
        $types = publishedResourceKeyTypes($resource, Post::class);
        $sent = (new $resource(new Post))->resolve(request());
        $reads[$resource] = [$types['counter_getviews'], $sent['counter_getviews'], $types['counter_startconst'], $sent['counter_startconst']];
    }

    // `counter_startconst` is the known gap "a receiver's body reads `static` as its declared class": `string` for a 9.
    expect($reads)->each->toBe(['unknown', 9, 'string', 9]);
});

it('types a published class\'s public property against that class, whose instances it describes', function () {
    $views = fn (string $class): string => resolve(AstEngine::class)->analyzePublicProperties($class)->properties[0]['type'];
    $bumped = new NinthViewCounter;
    $bumped->bump();

    expect([$views(StartedViewCounter::class), $views(NinthViewCounter::class)])->toBe(['string', 'string | number'])
        ->and(json_encode(new NinthViewCounter))->toBe('{"views":"x"}')
        ->and(json_encode($bumped))->toBe('{"views":9}');
});

/**
 * The given keys a BaseHelperLevelResource class publishes, each with what it sends as built and once raise() has run.
 *
 * @param  class-string<BaseHelperLevelResource>  $resource
 * @param  list<string>  $keys
 * @return array<string, array{string, mixed, mixed}>
 */
function helperLevelReads(string $resource, array $keys): array
{
    $types = publishedResourceKeyTypes($resource, Post::class);
    $sent = (new $resource(new Post))->resolve(request());
    $raised = (new $resource(new Post))->raise()->resolve(request());

    return array_combine($keys, array_map(fn (string $key): array => [$types[$key], $sent[$key], $raised[$key]], $keys));
}

// One test reads all three, so a helper body memoised once per `class@method` would answer every sibling alike.
it('reads `static` in a helper a private `$this->` or a `self::` call reaches as the caller\'s runtime class', function () {
    $keys = ['via_private', 'q_private', 'via_self', 'const_private', 'const_self', 'shaped_private', 'via_protected', 'via_static', 'via_this_static'];
    $expected = fn (string $level, string $constant, mixed $sent): array => array_combine($keys, [
        [$level, 'x', $sent], [$level, 'x', $sent], [$level, 'x', $sent], [$constant, $sent, $sent], [$constant, $sent, $sent],
        ['{ l: '.$level.' }', ['l' => 'x'], ['l' => $sent]], [$level, 'x', $sent], [$level, 'x', $sent], [$level, 'x', $sent],
    ]);
    $reads = [];

    foreach ([BaseHelperLevelResource::class, SevenHelperLevelResource::class, TrueHelperLevelResource::class] as $resource) {
        $reads[$resource] = helperLevelReads($resource, $keys);
    }

    expect($reads)->toBe([
        BaseHelperLevelResource::class => $expected('string', 'string', 'base'),
        SevenHelperLevelResource::class => $expected('string | number', 'number', 7),
        TrueHelperLevelResource::class => $expected('string | boolean', 'boolean', true),
    ]);
});

// Each class is read in one test, so a body read once per `class@method` would answer every sibling alike.
it('reads `static` in a body a non-static call through an ancestor\'s name or a `static` return reaches as the runtime class', function () {
    $keys = ['via_named', 'const_named', 'static_named', 'me_const', 'me_helper', 'me_private_const'];
    $expected = fn (string $level, string $constant, mixed $sent): array => array_combine($keys, [
        [$level, 'x', $sent], [$constant, $sent, $sent], ['string', 'base', 'base'],
        [$constant, $sent, $sent], [$level, 'x', $sent], [$constant, $sent, $sent],
    ]);
    $reads = [];

    foreach ([BaseHelperLevelResource::class, SevenHelperLevelResource::class, TrueHelperLevelResource::class] as $resource) {
        $reads[$resource] = helperLevelReads($resource, $keys);
    }

    expect($reads)->toBe([
        BaseHelperLevelResource::class => $expected('string', 'string', 'base'),
        SevenHelperLevelResource::class => $expected('string | number', 'number', 7),
        TrueHelperLevelResource::class => $expected('string | boolean', 'boolean', true),
    ]);
});

it('reads a call through an unrelated class\'s name on that class, which PHP runs on no other object', function () {
    $sibling = fn (string $resource): string => resolve(AstEngine::class)
        ->analyze($resource, 'siblingConst', Post::class)->properties[0]['type'];

    expect([$sibling(SevenHelperLevelResource::class), $sibling(TrueHelperLevelResource::class)])->toBe(['boolean', 'boolean'])
        ->and(new TrueHelperLevelResource(new Post)->siblingConst())->toBe(['sibling_const' => true])
        ->and(fn () => new SevenHelperLevelResource(new Post)->siblingConst())->toThrow(Error::class, 'cannot be called statically');
});

// A fresh app per row, so the first route's read is the one a shared entry would hand the second.
it('reads one body through a receiver and through `$this->` apart, whichever comes first', function (string $method) {
    app()->register(ResourceMacroServiceProvider::class, force: true);
    $types = [];

    foreach (resolve(AstEngine::class)->analyze(BaseHelperLevelResource::class, $method, Post::class)->properties as $property) {
        $types[$property['name']] = $property['type'];
    }

    $held = new BaseHelperLevelResource(new Post)->raise()->held();

    expect($types)->toMatchArray([
        'held' => 'unknown', 'own' => 'string', 'held_shape' => '{ l: unknown }', 'own_shape' => '{ l: string }',
        'held_macro' => 'unknown', 'own_macro' => 'string',
    ])
        ->and(new BaseHelperLevelResource(new Post)->raise()->$method())
        ->toMatchArray(['own' => 'base', 'own_shape' => ['l' => 'base'], 'own_macro' => 'base'])
        ->and([$held->publicHelper(), $held->publicShape(), $held->viaMacro()])->toBe(['x', ['l' => 'x'], 'x']);
})->with(['a receiver first' => ['receiverFirst'], '`$this->` first' => ['thisFirst']])
    ->after(fn () => JsonResource::flushMacros());

// Read assumed first in one process, so a known read sharing its entry would answer as it does.
it('keeps an assumed runtime class through an inherited body and the helpers it calls', function () {
    $types = fn (bool $assumes): array => array_column(resolve(AstEngine::class)
        ->analyzeMethod(SevenHelperLevelResource::class, 'toArray', Post::class, assumesRuntime: $assumes)->properties, 'type', 'name');

    expect($types(true))->toMatchArray(['via_protected' => 'unknown', 'via_private' => 'unknown', 'const_self' => 'number'])
        ->and($types(false))->toMatchArray(['via_protected' => 'string | number', 'via_private' => 'string | number', 'const_self' => 'number']);
});

it('reads a spread response as assumed only where it is the class `static` names in an assumed scope', function () {
    $type = fn (string $method, bool $assumes): string => array_column(resolve(AstEngine::class)
        ->analyzeMethod(SevenHelperLevelResource::class, $method, Post::class, assumesRuntime: $assumes)->properties, 'type', 'name')['via_private'];

    expect([$type('spreadOwn', true), $type('spreadOwn', false), $type('spreadSibling', true)])
        ->toBe(['unknown', 'string | number', 'string | boolean'])
        ->and(new SevenHelperLevelResource(new Post)->spreadOwn()['via_private'])->toBe('x')
        ->and(new SevenHelperLevelResource(new Post)->spreadSibling()['via_private'])->toBe('x');
});

// The object a forwarded call or a method macro runs on is only known by a class it may extend.
it('declines a `static` write in a model a resource forwards to, and in a method macro\'s object', function () {
    app()->register(ResourceMacroServiceProvider::class, force: true);
    $bumped = new NinthViewCountingModel()->bump();

    expect(publishedResourceKeyTypes(ViewCountingResource::class, ViewCountingModel::class))->toBe(['views' => 'unknown'])
        ->and(new ViewCountingResource($bumped)->resolve(request()))->toBe(['views' => 9])
        ->and(resolve(AstEngine::class)->analyze(BasePrivateLevelResource::class, 'macroViews', Post::class)->properties[0]['type'])
        ->toBe('unknown')
        ->and(new BasePrivateLevelResource(new Post)->macroViews())->toBe(['views' => 9]);
})->after(fn () => JsonResource::flushMacros());
