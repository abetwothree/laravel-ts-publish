<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Members\SubjectPropertyTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\AppendedTagsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BaseHelperLevelResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BasePrivateLevelResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BaseWrittenTierResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BuiltInstanceResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BuiltPrivateResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\DeclaredListCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\DeclaredReadsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\DeclaredSpreadListCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ForwardedBuildResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ForwardedBuildSiblingResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\LateMethodResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\LateReadsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ModelHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\NewParentResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\NumberTierResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ParameterTierResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SelfHelperPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SevenHelperLevelResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SevenLevelResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SilverTierResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StaticSyntaxResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\TrueHelperLevelResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\TrueLevelResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\SelfHelperPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Providers\ResourceMacroServiceProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\AliasedTallyChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\BumpingBase;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\CapturedWriteChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\CoalescedCaptureChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\EnclosedWriteChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ExcludedTallyChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\FannedHelperPayload;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ForwardedFreshPayload;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\InheritedHelperPayload;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\LevelAliasChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\MeasuredSizeChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\NestedCaptureChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\NinthViewCounter;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\OverriddenBumpChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\PairedWrapPayload;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ParentBumpChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\StartedViewCounter;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\StaticSyntaxPayload;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\SwappedPairPayload;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\TraitAliasedTallyChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\UncalledCaptureChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\UntypedTallyChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\WrappedChainPayload;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\WrappedSizeChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\WrittenTallyChild;
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
use Workbench\App\Http\Resources\TieredNoticeResource;
use Workbench\App\Models\Post;
use Workbench\App\ValueObjects\PanelPayload;
use Workbench\App\ValueObjects\TraitPanelPayload;

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

it('reads the declaring class\'s private method on an instance an inherited body builds as the runtime class', function () {
    expect(publishedResourceKeyTypes(BuiltPrivateResource::class, Post::class))->toBe([
        'late_built' => '{ base_label: number }', 'late_made' => '{ base_label: number }',
    ])->and((new BuiltPrivateResource(new Post))->resolve(request()))->toBe([
        'late_built' => ['base_label' => 1], 'late_made' => ['base_label' => 1],
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

it('types an untyped property a subclass writes through a typed setter as its default joined with the setter\'s type', function () {
    expect(analyzedKeyTypes(TieredNoticeResource::class))
        ->toBe(['headline' => 'string | number', 'label' => 'string', 'tier' => 'string', 'base_only' => 'number'])
        ->and((new TieredNoticeResource(null))->resolve(request()))
        ->toBe(['headline' => 0, 'label' => 'base', 'tier' => 'gold', 'base_only' => 1])
        ->and((new TieredNoticeResource(null))->withTier('silver')->resolve(request())['tier'])->toBe('silver');
});

// A write that reads the property it sets reads its default and constant writes there, and no later read may replay
// that. Read through `labelled()` first, `copyLabel()` reads that helper again with the label as it held before.
it('types an untyped property\'s non-constant writes in the scope of the method that holds each', function () {
    $written = new WrittenTallyChild()->setTier(5)->keepTier()->bump()->tick()->relabel()->stress();
    $child = new ReflectionClass(WrittenTallyChild::class);

    expect(analyzedKeyTypes(WrittenTallyChild::class))->toBe([
        'tier' => 'string | number',
        'count' => 'number',
        'label' => 'string | number',
        'labelled' => '{ label: string | number }',
    ])->and(resolve(SubjectPropertyTypeResolver::class)->resolve($child, 'tier', $child)['type'] ?? null)->toBe('string | number')
        ->and(json_encode(new WrittenTallyChild()->toArray()))->toBe('{"tier":"gold","count":0,"label":"draft","labelled":{"label":"draft"}}')
        ->and(json_encode($written->toArray()))->toBe('{"tier":5,"count":2,"label":"draft!","labelled":{"label":"draft!"}}')
        ->and(json_encode(new WrittenTallyChild()->setLabel(3)->copyLabel()->toArray()['labelled']))->toBe('{"label":3}');
});

it('reads a helper a write calls and the payload reads first with the property as it was before the write', function () {
    expect(analyzedKeyTypes(MeasuredSizeChild::class))->toBe(['h' => 'string | number', 'size' => 'string | number'])
        ->and(json_encode(new MeasuredSizeChild()->toArray()))->toBe('{"h":"none","size":"none"}')
        ->and(json_encode(new MeasuredSizeChild()->resize()->toArray()))->toBe('{"h":4,"size":4}')
        ->and(json_encode(new MeasuredSizeChild()->resize()->keep()->toArray()))->toBe('{"h":4,"size":4}');
});

it('reads a helper a write calls with the property cut where reading it as the joined type would join a new one', function () {
    $child = new ReflectionClass(WrappedSizeChild::class);
    $helperFirst = analyzedKeyTypes(WrappedSizeChild::class)['h'];
    resolve(AnalysisMemo::class)->reset();
    resolve(SubjectPropertyTypeResolver::class)->resolve($child, 'size', $child);

    expect([$helperFirst, analyzedKeyTypes(WrappedSizeChild::class)['h']])->toBe(['string | { v: unknown }', 'string | { v: unknown }'])
        ->and(json_encode(new WrappedSizeChild()->wrap()->toArray()['h']))->toBe('{"v":"none"}')
        ->and(json_encode(new WrappedSizeChild()->wrap()->wrap()->toArray()['h']))->toBe('{"v":{"v":"none"}}');
});

it('reads a property typed inside another\'s re-entry with the helpers under way cut, as without the re-entry', function () {
    expect(analyzedKeyTypes(PairedWrapPayload::class)['h'])->toBe('{ p: string | { v: unknown }; q: string | { v: unknown } }')
        ->and(json_encode(new PairedWrapPayload()->wrapQ()->wrapQ()->copyQ()->toArray()['h']))
        ->toBe('{"p":{"v":{"v":"none"}},"q":{"v":{"v":"none"}}}');
});

it('keeps a property read after a helper that typed it inside a re-entry, which reused nothing that re-entry cut', function () {
    expect(analyzedKeyTypes(SwappedPairPayload::class))->toMatchArray(['p' => 'string | number', 'q' => 'number | string'])
        ->and(json_encode(new SwappedPairPayload()->swapP()->toArray()))->toBe('{"mp":1,"mq":1,"p":1,"q":1}')
        ->and(json_encode(new SwappedPairPayload()->swapQ()->toArray()))->toBe('{"mp":"a","mq":"a","p":"a","q":"a"}');
});

it('reads properties written through each other\'s helpers as the cut read does, in work linear in keys and writes', function () {
    $memo = resolve(AnalysisMemo::class);
    $memo->reset();
    $fanned = analyzedKeyTypes(FannedHelperPayload::class);
    $fannedWork = $memo->computations();
    $memo->reset();
    $wrapped = analyzedKeyTypes(WrappedChainPayload::class);
    $wrappedWork = $memo->computations();
    $chain = 'string | '.str_repeat('{ v: string | ', 6).'{ v: unknown }'.str_repeat(' }', 6);
    $wrapAll = fn (WrappedChainPayload $payload): WrappedChainPayload => $payload->w6()->w5()->w4()->w3()->w2()->w1()->w0();

    expect($fanned)->toBe(array_fill_keys(['h0', 'h1', 'h2', 'h3'], 'string | unknown[]'))
        ->and(json_encode(new FannedHelperPayload()->w0()->w0()->toArray()['h0']))->toBe('[["none","none","none","none"],"none","none","none"]')
        ->and($wrapped)->toBe(array_fill_keys(['h0', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'], $chain))
        ->and(json_encode($wrapAll(new WrappedChainPayload)->toArray()['h0']))->toBe('{"v":{"v":{"v":{"v":{"v":{"v":{"v":"none"}}}}}}}')
        // Each key reads each helper call and property its writes reach at most twice: keys × (calls + properties) × 2.
        ->and($fannedWork)->toBeLessThanOrEqual(4 * (16 + 4) * 2)
        ->and($wrappedWork)->toBeLessThanOrEqual(7 * (7 + 7) * 2);
});

// The known gap: the engine cannot type an untyped parameter, which may hold anything.
it('leaves an untyped property `unknown` where a subclass writes it an untyped parameter', function () {
    expect(analyzedKeyTypes(UntypedTallyChild::class)['tier'])->toBe('unknown')
        ->and(json_encode(new UntypedTallyChild()->setTier(['x' => 1])->toArray()['tier']))->toBe('{"x":1}');
});

it('skips a write in a trait method the class overrides, which never runs', function () {
    expect(analyzedKeyTypes(OverriddenBumpChild::class)['tier'])->toBe('string')
        ->and(tap(new OverriddenBumpChild)->bump()->toArray()['tier'])->toBe('silver');
});

it('types a trait method the class overrides but runs under an alias in the trait method\'s own scope', function () {
    expect(analyzedKeyTypes(AliasedTallyChild::class)['tier'])->toBe('string | number')
        ->and(new AliasedTallyChild()->setTierNumber(5)->toArray()['tier'])->toBe(5);
});

// The alias is the used trait's, so the class's own getTraitAliases() does not list it.
it('types a trait method an outer trait overrides but runs under an alias that trait declares', function () {
    expect(analyzedKeyTypes(TraitAliasedTallyChild::class)['tier'])->toBe('string | number')
        ->and(new TraitAliasedTallyChild()->setTierNumber(5)->toArray()['tier'])->toBe(5);
});

it('types a trait method `insteadof` excludes but the class runs under an alias', function () {
    expect(analyzedKeyTypes(ExcludedTallyChild::class)['tier'])->toBe('string | number')
        ->and(json_encode([
            new ExcludedTallyChild()->setTier('x')->toArray()['tier'],
            new ExcludedTallyChild()->setTierNumber(5)->toArray()['tier'],
        ]))->toBe('["x",5]');
});

// `baseLevel()` runs the ancestor's copy, where `self` names the ancestor, so the subclass's LEVEL is never written.
it('skips the subclass\'s own copy of a trait method it overrides, though an ancestor\'s alias runs the body', function () {
    expect(analyzedKeyTypes(LevelAliasChild::class)['tier'])->toBe('string | number')
        ->and(json_encode([
            tap(new LevelAliasChild)->baseLevel()->toArray()['tier'],
            tap(new LevelAliasChild)->level()->toArray()['tier'],
        ]))->toBe('[5,"silver"]');
});

it('keeps the writes of an ancestor method the subclass overrides, which parent:: may still run', function () {
    expect(analyzedKeyTypes(ParentBumpChild::class))->toBe(['tier' => 'string | number'])
        ->and(json_encode(tap(new ParentBumpChild)->bump()->toArray()))->toBe('{"tier":"silver"}')
        ->and(json_encode(tap(new BumpingBase)->bump()->toArray()))->toBe('{"tier":5}');
});

// A callback's parameter shadows its method's `string $x`, so `last` and `arrowed` read the callback's `int`.
it('types a write a callback holds with the callback\'s own parameters and captures', function () {
    expect(analyzedKeyTypes(EnclosedWriteChild::class))->toBe(['last' => 'string | number', 'arrowed' => 'string | number', 'local' => 'string | number'])
        ->and(json_encode(new EnclosedWriteChild()->track('s')->trackArrow('s')->viaLocal('abc')->toArray()))
        ->toBe('{"last":2,"arrowed":4,"local":3}')
        ->and(analyzedKeyTypes(CapturedWriteChild::class))->toBe(['used' => 'string | number', 'anon' => 'string', 'loose' => 'unknown'])
        ->and(json_encode(new CapturedWriteChild()->capture()->anonymous()->loosen()->toArray()))
        ->toBe('{"used":7,"anon":"none","loose":1}');
});

// A by-reference capture reads the local as the closure runs, past a later write; a static closure has no `$this`.
it('reads a nested callback in its outer callback\'s scope, and skips a write a static callback holds', function () {
    expect(analyzedKeyTypes(NestedCaptureChild::class))->toBe(['used' => 'unknown', 'anon' => 'string', 'loose' => 'string | number'])
        ->and(json_encode(new NestedCaptureChild()->captureByReference()->refuse()->nest()->toArray()))
        ->toBe('{"used":"late","anon":"none","loose":3}');
});

// O3: a closure may run wherever it goes, so its write joins even where the body that builds it never calls it.
it('joins a write a callback holds even where the callback is never called', function () {
    expect(analyzedKeyTypes(UncalledCaptureChild::class)['used'])->toBe('string | number')
        ->and(json_encode(new UncalledCaptureChild()->park()->toArray()['used']))->toBe('"none"');
});

// `??` leaves out an arm it cannot type, so a callback's read through it would publish only the fallback PHP may skip.
it('leaves `unknown` a write a callback holds that reads an untypable value through `??`', function () {
    expect(analyzedKeyTypes(CoalescedCaptureChild::class))->toBe(['used' => 'unknown', 'anon' => 'string', 'loose' => 'unknown'])
        ->and(json_encode(new CoalescedCaptureChild()->coalesceCapture()->coalesceParameter()->toArray()))
        ->toBe('{"used":true,"anon":"none","loose":true}');
});

// An element unset(), an append onto an object, an append beside a key write, and a computed key keep the known gap.
it('types an untyped array a subclass appends to or keys into as its default with each element written', function () {
    $resource = fn (): AppendedTagsResource => new AppendedTagsResource(null);

    expect(publishedResourceKeyTypes(AppendedTagsResource::class, Post::class))->toBe([
        'tags' => 'string[]',
        'empty' => 'string[]',
        'keyed' => '{ a: number; b?: number }',
        'counts' => '{ a: number | string }',
        'mixed' => '(string | number)[]',
        'pruned' => 'unknown',
        'labels' => 'unknown',
        'both' => 'unknown',
        'dynamic' => 'unknown',
    ])->and(json_encode($resource()->resolve(request())))->toBe(
        '{"tags":["base"],"empty":[],"keyed":{"a":1},"counts":{"a":1},"mixed":["m"],"pruned":["p"],"labels":{"a":"x"},'
        .'"both":["a"],"dynamic":{"a":1}}',
    )->and(json_encode($resource()->tag('x')->resolve(request())))->toBe(
        '{"tags":["base","x"],"empty":["x"],"keyed":{"a":1,"b":2},"counts":{"a":"many"},"mixed":["m","x"],"pruned":["p"],'
        .'"labels":{"a":"x","0":"x"},"both":{"0":"a","1":"x","k":1},"dynamic":{"a":1,"x":1}}',
    )->and(json_encode($resource()->renumber()->tag('x')->prune()->resolve(request())))->toBe(
        '{"tags":["base","x"],"empty":["x"],"keyed":{"a":1,"b":2},"counts":{"a":"many"},"mixed":[1,2,"x"],"pruned":["q"],'
        .'"labels":{"a":"x","0":"x"},"both":{"0":"a","1":"x","k":1},"dynamic":{"a":1,"x":1}}',
    );
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

it('reads a model column in a helper a private `$this->` or a `self::` call reaches as the runtime class\'s model', function () {
    $post = new Post;
    $post->forceFill(['id' => 1, 'title' => 'x']);

    expect(publishedResourceKeyTypes(ModelHelperResource::class, Post::class))->toBe([
        'via_protected' => 'string', 'via_self' => 'string', 'via_self_shape' => '{ t: string }', 'prot' => 'string',
    ])->and((new ModelHelperResource($post))->resolve(request()))->toBe([
        'via_protected' => 'x', 'via_self' => 'x', 'via_self_shape' => ['t' => 'x'], 'prot' => 'x',
    ]);
});

it('reads a column in a helper a model\'s ancestor reaches through `self::` or a private `$this->` as the runtime model\'s', function () {
    $post = new SelfHelperPost;
    $post->forceFill(['id' => 1, 'title' => 'x']);

    expect(publishedResourceKeyTypes(SelfHelperPostResource::class, SelfHelperPost::class))->toBe([
        'via_self' => 'string', 'via_self_shape' => '{ t: string; picked: { title: string } }',
        'via_self_only' => '{ title: string }', 'via_private' => 'string',
    ])->and((new SelfHelperPostResource($post))->resolve(request()))->toBe([
        'via_self' => 'x', 'via_self_shape' => ['t' => 'x', 'picked' => ['title' => 'x']], 'via_self_only' => ['title' => 'x'], 'via_private' => 'x',
    ]);
});

it('reads a `+` on a method only the runtime class declares, in a helper a `self::` call reaches, as PHP joins it', function () {
    expect(publishedResourceKeyTypes(LateMethodResource::class, Post::class))->toBe(['joined' => '{ c: boolean; x: number }'])
        ->and((new LateMethodResource(new Post))->resolve(request()))->toBe(['joined' => ['c' => true, 'x' => 1]]);
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

it('reads self:: and parent:: as the declaring class in inherited and trait bodies', function () {
    expect(analyzedKeyTypes(PanelPayload::class))->toMatchArray(['own' => 'string | number', 'root' => 'number'])
        ->and(analyzedKeyTypes(TraitPanelPayload::class))->toBe(['own' => 'string', 'root' => 'number', 'late' => 'string'])
        ->and(analyzedKeyTypes(InheritedHelperPayload::class))->toBe([
            'self' => 'string | number', 'parent' => 'number', 'static' => 'string',
            'v' => '{ self: string | number; parent: number; static: string }',
        ]);
});

it('sends what self:: and parent:: name in inherited and trait bodies', function () {
    expect(new PanelPayload()->toArray())->toMatchArray(['own' => 0, 'root' => 1])
        ->and(new TraitPanelPayload()->toArray())->toBe(['own' => 'own', 'root' => 1, 'late' => 'own'])
        ->and(new InheritedHelperPayload()->toArray())->toBe([
            'self' => 1, 'parent' => 1, 'static' => 'x', 'v' => ['self' => 1, 'parent' => 1, 'static' => 'x'],
        ]);
});

// `stash` and `trait_stash` reach the ancestor's by-reference fill(), which may leave anything in them.
it('reads self:: and parent:: in a helper an ancestor declares as that ancestor and its parent', function () {
    $post = new Post;
    $post->forceFill(['title' => 'x']);

    expect(publishedResourceKeyTypes(DeclaredReadsResource::class, Post::class))->toBe([
        'root' => 'number', 'base_part' => 'number', 'base_key' => 'boolean', 'built' => '{ base_part: number }',
        'built_titled' => '{ title: string }', 'views' => 'number', 'level' => 'string', 'stash' => 'unknown',
        'trait_stash' => 'unknown',
    ])->and((new DeclaredReadsResource($post))->resolve(request()))->toBe([
        'root' => 1, 'base_part' => 1, 'base_key' => true, 'built' => ['base_part' => 1], 'built_titled' => ['title' => 'x'],
        'views' => 1.5, 'level' => 'root', 'stash' => ['seed' => 1, 'filled' => true],
        'trait_stash' => ['seed' => 1, 'filled' => true],
    ]);
});

it('reads parent::toArray() in a collection helper as the list the declaring class\'s parent sends', function () {
    expect(analyzedKeyTypes(DeclaredListCollection::class))->toBe(['items' => 'PostResource[]'])
        ->and(analyzedKeyTypes(DeclaredSpreadListCollection::class))->toBe(['[key: number]' => 'PostResource', 'count' => 'number'])
        ->and((new DeclaredListCollection(collect()))->resolve(request()))->toBe(['items' => []])
        ->and((new DeclaredSpreadListCollection(collect()))->resolve(request()))->toBe(['count' => 0]);
});

// JsonResource's make() and collection() build `new static`, which a `self::` call forwards as the runtime class.
it('reads a self:: builder in an ancestor\'s helper as the runtime class it forwards, and new self as the declaring class', function () {
    $post = new Post;
    $post->forceFill(['id' => 1, 'title' => 'x']);
    $sent = (new ForwardedBuildResource($post))->resolve(request());

    expect(publishedResourceKeyTypes(ForwardedBuildResource::class, Post::class))->toBe([
        'made' => 'ForwardedBuildResource', 'listed' => 'ForwardedBuildResource[]',
        'made_summary' => '{ child_summary: boolean }', 'made_titled' => '{ title: string }',
        'new_summary' => '{ base_summary: number }',
    ])->and($sent['made'])->toBeInstanceOf(ForwardedBuildResource::class)
        ->and($sent['listed']->collects)->toBe(ForwardedBuildResource::class)
        ->and($sent['made_summary'])->toBe(['child_summary' => true])
        ->and($sent['made_titled'])->toBe(['title' => 'x'])
        ->and($sent['new_summary'])->toBe(['base_summary' => 1]);
});

it('reads the self:: builder two siblings share as each one\'s own class, whichever is read first', function () {
    $read = fn (string $class): array => publishedResourceKeyTypes($class, Post::class);
    $child = ['made' => 'ForwardedBuildResource', 'listed' => 'ForwardedBuildResource[]', 'made_summary' => '{ child_summary: boolean }'];
    $sibling = ['made' => 'ForwardedBuildSiblingResource', 'listed' => 'ForwardedBuildSiblingResource[]', 'made_summary' => '{ sibling_summary: string }'];
    $childFirst = [$read(ForwardedBuildResource::class), $read(ForwardedBuildSiblingResource::class)];
    resolve(AnalysisMemo::class)->reset();
    $siblingFirst = [$read(ForwardedBuildSiblingResource::class), $read(ForwardedBuildResource::class)];

    expect($childFirst[0])->toMatchArray($child)->and($childFirst[1])->toMatchArray($sibling)
        ->and($siblingFirst[0])->toMatchArray($sibling)->and($siblingFirst[1])->toMatchArray($child)
        ->and((new ForwardedBuildSiblingResource(new Post))->resolve(request())['made_summary'])->toBe(['sibling_summary' => 'x']);
});

it('reads a static return a self:: or parent:: call reaches as the runtime class it forwards', function () {
    expect(analyzedKeyTypes(ForwardedFreshPayload::class))->toBe([
        'self_fresh' => '{ b: boolean }', 'new_self' => '{ a: number }', 'parent_fresh' => '{ b: boolean }',
    ])->and(new ForwardedFreshPayload()->toArray())->toBe([
        'self_fresh' => ['b' => true], 'new_self' => ['a' => 1], 'parent_fresh' => ['b' => true],
    ]);
});

// Laravel's own JsonResource publishes no type, so a `new` of it stays unknown rather than import a framework class.
it('leaves a new of the framework parent unknown, while parent::make() builds the runtime class', function () {
    $post = new Post;
    $post->forceFill(['id' => 1, 'title' => 'x']);
    $analysis = resolve(AstEngine::class)->analyze(NewParentResource::class, 'toArray', Post::class);
    $sent = (new NewParentResource($post))->resolve(request());

    expect(array_column($analysis->properties, 'type', 'name'))->toMatchArray([
        'built' => 'unknown', 'made' => 'NewParentResource', 'listed' => 'NewParentResource[]',
    ])->and(array_column($analysis->properties, 'type', 'name')['resolved'])->not->toContain('JsonResource')
        ->and(array_keys($analysis->typeImports))->each->not->toContain('illuminate')
        ->and(json_decode((string) json_encode($sent['built']), true))->toBe(['id' => 1, 'title' => 'x'])
        ->and($sent['resolved'])->toBe(['id' => 1, 'title' => 'x'])
        ->and($sent['made'])->toBeInstanceOf(NewParentResource::class)
        ->and($sent['listed']->collects)->toBe(NewParentResource::class);
});
