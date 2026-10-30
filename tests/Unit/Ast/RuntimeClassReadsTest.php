<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\RuntimeClass;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Members\SubjectPropertyTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedResourceRegistry;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\AppendedTagsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BaseHelperLevelResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BasePrivateLevelResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BaseWrittenTierResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BuiltFactsCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BuiltFactsItemCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BuiltFactsItemResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BuiltFactsOtherCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BuiltFactsOtherItemCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BuiltFactsPostsOtherCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BuiltFactsRawOtherCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BuiltFactsSubCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BuiltInstanceResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BuiltLabelBaseResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BuiltLabelHolderResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BuiltPrivateResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BuiltSortsCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\CopiedTagsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\DeclaredListCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\DeclaredReadsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\DeclaredSpreadListCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ForwardedBuildResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ForwardedBuildSiblingResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\KeptReorderedTagsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\KeptTagsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\LateMethodResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\LateReadsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ModelHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\NewParentResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\NumberTierResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\OtherChainResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ParameterTierResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PrivateChainBaseResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PrivateChainResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\RenumberedTagsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReorderedTagsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SelfHelperPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SevenHelperLevelResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SevenLevelResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SilverTierResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadingChildResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StaticSyntaxResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\TrueHelperLevelResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\TrueLevelResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\SelfHelperPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Providers\ResourceMacroServiceProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\AbstractSpreadBase;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\AliasedTallyChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\AppendedTagsPayload;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\BumpingBase;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\CapturedWriteChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ChainedCaptureChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\CoalescedCaptureChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\CyclingOverwriteTop;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\CyclingOverwriteValue;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\CyclingSpreadBase;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\EnclosedWriteChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ExcludedTallyChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\FannedHelperPayload;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ForwardedFreshPayload;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\InheritedHelperPayload;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\LevelAliasChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\MeasuredSizeChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\NamedPickHolder;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\NestedCaptureChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\NinthViewCounter;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\NumberedTagsPayload;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\OverriddenBumpChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\OverwrittenSpreadArgument;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\OverwrittenSpreadChained;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\OverwrittenSpreadDeeper;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\OverwrittenSpreadDelegated;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\OverwrittenSpreadHelper;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\OverwrittenSpreadLiteral;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\OverwrittenSpreadNested;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\OverwrittenSpreadNestedValue;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\OverwrittenSpreadTop;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\OverwrittenSpreadValue;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\PairedWrapPayload;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ParentBumpChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\PartlyReadSpreadPayload;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\PrivateSpreadBase;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReboundImportPayload;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReorderedTagsPayload;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\SerializingSpreadChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\SpreadingChild;
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
use Workbench\App\Http\Resources\NoticeResource;
use Workbench\App\Http\Resources\TagBoardResource;
use Workbench\App\Http\Resources\TieredNoticeResource;
use Workbench\App\Models\Post;
use Workbench\App\ValueObjects\PanelPayload;
use Workbench\App\ValueObjects\TraitPanelPayload;

/**
 * What StaticSyntaxResource sends over a fresh Post, its inherited `toArray()` run as PHP runs it.
 *
 * @return array<string, mixed>
 */
function staticSyntaxResponse(): array
{
    return (new StaticSyntaxResource(new Post))->resolve(request());
}

/**
 * The keys a class publishes, read under an alarm where pcntl has one, so a read that never ends fails the test after
 * `$seconds` instead of hanging the suite.
 *
 * @param  class-string  $class
 * @return array<string, string>
 */
function analyzedKeyTypesWithin(int $seconds, string $class): array
{
    if (! function_exists('pcntl_alarm')) {
        return analyzedKeyTypes($class);
    }

    $async = pcntl_async_signals(true);
    pcntl_signal(SIGALRM, static fn () => throw new RuntimeException("ran past {$seconds} s"));
    pcntl_alarm($seconds);

    try {
        return analyzedKeyTypes($class);
    } catch (Throwable $runaway) {
        // Rethrown without its trace, which a runaway read leaves deep enough to stall the failure report.
        throw new RuntimeException("Reading {$class} did not end: {$runaway->getMessage()}");
    } finally {
        pcntl_alarm(0);
        pcntl_signal(SIGALRM, SIG_DFL);
        pcntl_async_signals($async);
    }
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
    $scope->runtime = RuntimeClass::known(new ReflectionClass(ReceiverChildDto::class));
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

// `new self` builds exactly the declaring class, so its method reads `static::LABEL` even in a receiver's assumed body.
it('knows the class a receiver\'s body builds, as an exact build, though the receiver\'s own class is only assumed', function () {
    expect(publishedResourceKeyTypes(BuiltLabelHolderResource::class, Post::class))->toBe(['fresh' => '{ label: string | number }'])
        ->and(json_encode((new BuiltLabelHolderResource(null))->resolve(request())))->toBe('{"fresh":{"label":"none"}}')
        ->and(json_encode(new BuiltLabelBaseResource(null)->relabel()->summary()))->toBe('{"label":5}');
});

// collectResource() maps the items a built collection is given into what it collects, unless they already are one.
it('reads a built collection\'s items as the class it collects where it maps them', function () {
    $sent = fn (string $collection, array $items): array => (new $collection(collect($items)))->resolve(request());

    expect(analyzedKeyTypes(BuiltFactsOtherCollection::class))->toBe(['summary' => '{ items: BuiltFactsItemResource[] }'])
        ->and(json_encode($sent(BuiltFactsOtherCollection::class, [1, 1])['summary']))->toBe('{"items":[{"a":1},{"a":1}]}')
        ->and(analyzedKeyTypes(BuiltSortsCollection::class))->toHaveKey('raw', '{ items: Record<string, BuiltFactsItemResource> }')
        ->and(json_encode($sent(BuiltSortsCollection::class, [1])['raw']))->toBe('{"items":{"k":{"a":1}}}');
});

// Items already what the built collection collects pass through, and the outer response's filter() re-indexes.
it('keeps the outer class\'s keys, re-indexing and passed-through items in a built collection\'s method', function () {
    $sent = fn (string $collection, array $items): array => (new $collection(collect($items)))->resolve(request());

    expect(analyzedKeyTypes(BuiltFactsCollection::class))->toBe(['summary' => '{ items: BuiltFactsItemResource[] }'])
        ->and(json_encode($sent(BuiltFactsCollection::class, [1, 1])['summary']))->toBe('{"items":[{"a":1},{"a":1}]}')
        ->and(analyzedKeyTypes(BuiltFactsSubCollection::class))->toBe(['summary' => '{ items: BuiltFactsSubItemResource[] }'])
        ->and(json_encode($sent(BuiltFactsSubCollection::class, [1])['summary']))->toBe('{"items":[{"s":"y"}]}')
        ->and(analyzedKeyTypes(BuiltSortsCollection::class))->toHaveKey('sorted', '{ letters: Record<number, string> }')
        ->and(json_encode($sent(BuiltSortsCollection::class, [1])['sorted']))->toBe('{"letters":{"1":"a","0":"b"}}');
});

// A built collection whose collects() picks no class keeps the items it is given, whatever its ancestor collects.
it('keeps the outer element where the built collection picks no class of its own', function () {
    $sent = fn (string $collection): string => json_encode((new $collection(collect([1])))->resolve(request())['summary']);
    $published = ['summary' => '{ items: BuiltFactsOtherItemResource[] }'];

    expect(analyzedKeyTypes(BuiltFactsPostsOtherCollection::class))->toBe($published)
        ->and($sent(BuiltFactsPostsOtherCollection::class))->toBe('{"items":[{"b":"x"}]}')
        ->and(analyzedKeyTypes(BuiltFactsRawOtherCollection::class))->toBe($published)
        ->and($sent(BuiltFactsRawOtherCollection::class))->toBe('{"items":[{"b":"x"}]}');
});

// An outer element the run cannot name may hold a subclass of what the built collection collects, which passes through.
it('keeps an outer element the run cannot name unknown in a built collection\'s method', function () {
    PublishedResourceRegistry::register([
        BuiltFactsItemResource::class, BuiltFactsItemCollection::class, BuiltFactsOtherItemCollection::class,
    ]);

    expect(analyzedKeyTypes(BuiltFactsOtherItemCollection::class))->toBe(['summary' => '{ items: unknown[] }'])
        ->and(json_encode((new BuiltFactsOtherItemCollection(collect([1])))->resolve(request())['summary']))
        ->toBe('{"items":[{"a":1}]}');
});

// From its own body PHP calls the declaring class's private chain() on a built subclass, over the public namesake.
it('reads a chain on an instance an inherited body builds through the declaring class\'s private method', function () {
    $sent = (new PrivateChainResource(null))->resolve(request());

    expect(publishedResourceKeyTypes(PrivateChainResource::class, Post::class))
        ->toHaveKey('nested?', 'PrivateChainResource')
        ->and($sent['nested'])->toBeInstanceOf(PrivateChainResource::class)
        ->and(json_encode($sent['nested']))->toBe('{"other":{"o":1}}');
});

// An object whose class does not extend the declaring class runs its own method, whatever the body declares private.
it('reads a chain on an unrelated built class through that class\'s own method', function () {
    $base = new ReflectionClass(PrivateChainBaseResource::class);
    $other = new ReflectionClass(OtherChainResource::class);

    expect(publishedResourceKeyTypes(PrivateChainResource::class, Post::class))
        ->toHaveKey('other', 'Record<string, number>')
        ->and(AnalysisScope::memberClassOf($base, $other, 'chain', true))->toBe($other)
        ->and((new PrivateChainResource(null))->resolve(request())['other'])->toBe(['o' => 1]);
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
    $scope->runtime = RuntimeClass::known(new ReflectionClass(ReceiverHolderChildDto::class));
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
    ])->and(resolve(SubjectPropertyTypeResolver::class)->resolve($child, 'tier', RuntimeClass::known($child))['type'] ?? null)->toBe('string | number')
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
    resolve(SubjectPropertyTypeResolver::class)->resolve($child, 'size', RuntimeClass::known($child));

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

it('reads properties written through each other\'s helpers as the cut read does, adding a read per call and property', function () {
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
        // The cut read computes 78 and 121, a write scan per property among them; the check and fallback add one read
        // per call and property, 2 × (16 + 4) on the fan and none on the chain, whose cycle closes inside the cut read.
        // The caps leave room above 118 and 121.
        ->and($fannedWork)->toBeLessThanOrEqual(160)
        ->and($wrappedWork)->toBeLessThanOrEqual(196);
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

// The inner closure imports from the outer one's scope, so `$n`, which the method never holds, reads the parameter.
it('reads a closure another closure holds through each one\'s imports in turn', function () {
    expect(analyzedKeyTypes(ChainedCaptureChild::class))->toBe(['used' => 'string | boolean', 'anon' => 'string', 'loose' => 'string | number'])
        ->and(json_encode(new ChainedCaptureChild()->chain()->toArray()))
        ->toBe('{"used":true,"anon":"none","loose":5}');
});

// The scan reads an import a closure reassigns as that local wherever the read sits, so `rebound` would type `5`; only
// the import the write reads counts, so `paired` keeps its type beside the `$w` its closure reassigns.
it('leaves `unknown` a write a callback holds that reads an import the callback, or one around it, reassigns', function () {
    expect(analyzedKeyTypes(ReboundImportPayload::class))->toBe([
        'rebound' => 'unknown', 'kept' => 'string | boolean', 'relayed' => 'unknown', 'arrowed' => 'unknown',
        'paired' => 'string | boolean',
    ])->and(json_encode(new ReboundImportPayload()->rebind()->keep()->relay()->arrow()->pair()->toArray()))
        ->toBe('{"rebound":true,"kept":true,"relayed":true,"arrowed":true,"paired":true}')
        ->and(json_encode(new ReboundImportPayload()->toArray()['paired']))->toBe('"none"');
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

// O2: a write may run in any order, any number of times, or not at all; Laravel re-indexes `pruned`'s numeric keys.
it('types an untyped array a subclass appends to or keys into as its default with each element written', function () {
    $resource = fn (): AppendedTagsResource => new AppendedTagsResource(null);

    expect(publishedResourceKeyTypes(AppendedTagsResource::class, Post::class))->toBe([
        'tags' => 'string[]',
        'empty' => 'string[]',
        'keyed' => '{ a: number; b?: number }',
        'counts' => '{ a: number | string }',
        'mixed' => '(string | number)[]',
        'pruned' => 'string[]',
        'labels' => '{ a: string } & Record<number, string>',
        'both' => 'string[] | ({ k?: number } & Record<number, string>)',
        'dynamic' => '{ a: number; [key: string]: number }',
        'keyedList' => '(string | number)[] | Record<string, string | number>',
        'nested' => '{ a: { b: number; c?: string } }',
        'refs' => '(string | number)[]',
        'dropped' => '{ a?: number; b: number }',
    ])->and(json_encode($resource()->resolve(request())))->toBe(
        '{"tags":["base"],"empty":[],"keyed":{"a":1},"counts":{"a":1},"mixed":["m"],"pruned":["p"],"labels":{"a":"x"},'
        .'"both":["a"],"dynamic":{"a":1},"keyedList":["l"],"nested":{"a":{"b":1}},"refs":["r"],"dropped":{"a":1,"b":2}}',
    )->and(json_encode($resource()->tag('x')->resolve(request())))->toBe(
        '{"tags":["base","x"],"empty":["x"],"keyed":{"a":1,"b":2},"counts":{"a":"many"},"mixed":["m","x"],"pruned":["p"],'
        .'"labels":{"a":"x","0":"x"},"both":{"0":"a","1":"x","k":1},"dynamic":{"a":1,"x":1},'
        .'"keyedList":{"0":"l","x":2},"nested":{"a":{"b":1,"c":"x"}},"refs":[1],"dropped":{"b":2}}',
    )->and(json_encode($resource()->renumber()->tag('x')->prune()->resolve(request())))->toBe(
        '{"tags":["base","x"],"empty":["x"],"keyed":{"a":1,"b":2},"counts":{"a":"many"},"mixed":[1,2,"x"],"pruned":["q"],'
        .'"labels":{"a":"x","0":"x"},"both":{"0":"a","1":"x","k":1},"dynamic":{"a":1,"x":1},'
        .'"keyedList":{"0":"l","x":2},"nested":{"a":{"b":1,"c":"x"}},"refs":[1],"dropped":{"b":2}}',
    );
});

it('keeps the keys an element unset() leaves in an untyped array a plain class sends as it holds it', function () {
    expect(analyzedKeyTypes(AppendedTagsPayload::class)['pruned'])->toBe('string[] | Record<number, string>')
        ->and(json_encode(new AppendedTagsPayload()->prune()->toArray()['pruned']))->toBe('{"1":"q"}');
});

it('keeps the keys an element unset() leaves in an untyped array a resource that preserves keys sends', function () {
    expect(publishedResourceKeyTypes(KeptTagsResource::class, Post::class)['pruned'])->toBe('string[] | Record<number, string>')
        ->and(json_encode((new KeptTagsResource(null))->prune()->resolve(request())['pruned']))->toBe('{"1":"q"}');
});

// PHP sends an array keyed only by numbers 0, 1, … as a list, and a resource re-indexes any keyed only by numbers.
it('publishes the list an untyped array an unset or a whole write leaves keyed only by numbers is sent as', function () {
    $resource = fn (): RenumberedTagsResource => new RenumberedTagsResource(null);

    expect(analyzedKeyTypes(AppendedTagsPayload::class)['renumbered'])
        ->toBe('{ a?: number; [key: string]: number | undefined } | number[]')
        ->and(json_encode(new AppendedTagsPayload()->renumber('0')->dropRenumbered()->toArray()['renumbered']))->toBe('[2]')
        ->and(analyzedKeyTypes(RenumberedTagsResource::class))->toBe([
            'renumbered' => '{ a?: number; [key: string]: number | undefined } | number[]',
            'numbered' => '{ a?: number; "5"?: string } | string[]',
            'swapped' => '{ a?: number; k?: number } & Record<number, string>',
            'fived' => '{ "5": string; k?: number } | string[]',
            'rows' => '({ x: number; y?: number } | { x: number })[]',
            'shifted' => '{ a?: number; "0"?: string; "1": string } | string[]',
            'gapped' => '{ a?: number; "0": string; "1"?: string; "2": string } | string[]',
            'extended' => '{ a?: number; "0": string; "1"?: number }',
        ])
        ->and(json_encode($resource()->resolve(request())))->toBe(
            '{"renumbered":{"a":1},"numbered":{"a":1},"swapped":{"a":1},"fived":["s"],"rows":[{"x":1},{"x":2}],'
            .'"shifted":{"a":1,"0":"x","1":"y"},"gapped":{"a":1,"0":"x","1":"y","2":"z"},"extended":{"a":1,"0":"x"}}',
        )
        ->and(json_encode($resource()->renumber('5')->dropRenumbered()->number()->dropNumbered()->swap()->dropShifted()
            ->dropGapped()->dropExtendedLetter()->resolve(request())))
        ->toBe(
            '{"renumbered":[2],"numbered":["s"],"swapped":["s"],"fived":["s"],"rows":[{"x":1},{"x":2}],"shifted":["y"],'
            .'"gapped":["x","z"],"extended":["x"]}',
        )
        ->and(json_encode($resource()->renumber('x')->number()->key()->keyFived()->extendRow()->extend()->dropExtendedLetter()
            ->resolve(request())))
        ->toBe(
            '{"renumbered":{"a":1,"x":2},"numbered":{"a":1,"5":"s"},"swapped":{"a":1,"k":1},"fived":{"5":"s","k":1},'
            .'"rows":[{"x":1,"y":2},{"x":2}],"shifted":{"a":1,"0":"x","1":"y"},"gapped":{"a":1,"0":"x","1":"y","2":"z"},'
            .'"extended":["x",5]}',
        )
        ->and(json_encode($resource()->swap()->key()->resolve(request())['swapped']))->toBe('{"5":"s","k":1}');
});

// A resource lists numbers in the order the array holds them, which a whole write may change; a plain class keys them.
it('publishes the list a resource sends an untyped array a whole write may hold with its numbers moved', function () {
    $resource = fn (): ReorderedTagsResource => new ReorderedTagsResource(null);
    $payload = fn (): ReorderedTagsPayload => new ReorderedTagsPayload;

    expect(analyzedKeyTypes(ReorderedTagsResource::class))->toBe([
        'reordered' => '{ a?: number; "0": string; "1": number } | (string | number)[]',
        'prepended' => '{ a?: number; "0": string; "1"?: number } | (string | number)[]',
        'lengthened' => '{ a?: number; "0": string; "1": number; "2"?: boolean } | (string | number | boolean)[]',
        'alike' => '{ a?: number; "0": string; "1": string }',
        'nested' => '{ a: { k?: number; "0": string; "1": number } | (string | number)[] }',
        'deep' => '{ a: { b: { k?: number; "0": string; "1": number } | (string | number)[] } }',
        'slid' => '{ a?: number; "0"?: string; "1": string; "2"?: string }',
    ])->and(json_encode($resource()->resolve(request())))->toBe(
        '{"reordered":{"a":1,"0":"x","1":5},"prepended":{"a":1,"0":"x"},"lengthened":{"a":1,"0":"x","1":5},'
        .'"alike":{"a":1,"0":"x","1":"y"},"nested":{"a":{"k":1,"0":"x","1":5}},'
        .'"deep":{"a":{"b":{"k":1,"0":"x","1":5}}},"slid":{"a":1,"0":"x","1":"y"}}',
    )->and(json_encode($resource()->dropLetters()->resolve(request())))->toBe(
        '{"reordered":["x",5],"prepended":["x"],"lengthened":["x",5],"alike":["x","y"],"nested":{"a":["x",5]},'
        .'"deep":{"a":{"b":["x",5]}},"slid":["x","y"]}',
    )->and(json_encode($resource()->reorder()->slide()->resolve(request())))->toBe(
        '{"reordered":{"a":1,"1":5,"0":"x"},"prepended":{"a":1,"1":5,"0":"x"},'
        .'"lengthened":{"a":1,"1":5,"0":"x","2":true},"alike":{"a":1,"1":"y","0":"x"},'
        .'"nested":{"a":{"k":1,"1":5,"0":"x"}},"deep":{"a":{"b":{"k":1,"1":5,"0":"x"}}},'
        .'"slid":{"a":1,"1":"y","2":"z"}}',
    )->and(json_encode($resource()->reorder()->slide()->dropLetters()->resolve(request())))->toBe(
        '{"reordered":[5,"x"],"prepended":[5,"x"],"lengthened":[5,"x",true],"alike":["y","x"],"nested":{"a":[5,"x"]},'
        .'"deep":{"a":{"b":[5,"x"]}},"slid":["y","z"]}',
    )->and(analyzedKeyTypes(ReorderedTagsPayload::class))->toBe([
        'reordered' => '{ a?: number; "0": string; "1": number }',
        'prepended' => '{ a?: number; "0": string; "1"?: number }',
        'lengthened' => '{ a?: number; "0": string; "1": number; "2"?: boolean }',
        'alike' => '{ a?: number; "0": string; "1": string }',
        'nested' => '{ a: { k?: number; "0": string; "1": number } }',
        'deep' => '{ a: { b: { k?: number; "0": string; "1": number } } }',
        'slid' => '{ a?: number; "0"?: string; "1": string; "2"?: string }',
    ])->and(json_encode($payload()->dropLetters()->toArray()))->toBe(
        '{"reordered":["x",5],"prepended":["x"],"lengthened":["x",5],"alike":["x","y"],"nested":{"a":["x",5]},'
        .'"deep":{"a":{"b":["x",5]}},"slid":["x","y"]}',
    )->and(json_encode($payload()->reorder()->slide()->dropLetters()->toArray()))->toBe(
        '{"reordered":{"1":5,"0":"x"},"prepended":{"1":5,"0":"x"},"lengthened":{"1":5,"0":"x","2":true},'
        .'"alike":{"1":"y","0":"x"},"nested":{"a":{"1":5,"0":"x"}},"deep":{"a":{"b":{"1":5,"0":"x"}}},'
        .'"slid":{"1":"y","2":"z"}}',
    )->and(analyzedKeyTypes(KeptReorderedTagsResource::class))
        ->toBe(['deep' => '{ a: { b: { k?: number; "0": string; "1": number } } }'])
        ->and(json_encode((new KeptReorderedTagsResource(null))->reorder()->dropLetters()->resolve(request())))
        ->toBe('{"deep":{"a":{"b":{"1":5,"0":"x"}}}}');
});

// Each copy is typed from the source's joined type, which keeps no order, so after `flip()` PHP sends `[5,"x"]`.
it('publishes the list a resource sends a copy of an untyped array a whole write may reorder', function () {
    $resource = fn (): CopiedTagsResource => new CopiedTagsResource(null);

    expect(analyzedKeyTypes(CopiedTagsResource::class))->toBe([
        'source' => '{ "0": string; "1": number; n: number }',
        'copied' => '{ "0": string; "1": number; n?: number } | (string | number)[]',
        'blank' => 'never[] | { "0": string; "1": number; n?: number } | (string | number)[]',
        'fetched' => '{ "0": string; "1": number; n?: number } | (string | number)[]',
    ])->and(json_encode($resource()->resolve(request())))->toBe(
        '{"source":{"0":"x","1":5,"n":1},"copied":{"0":"x","1":5,"n":1},"blank":[],"fetched":{"0":"x","1":5,"n":1}}',
    )->and(json_encode($resource()->copy()->resolve(request())))->toBe(
        '{"source":{"0":"x","1":5,"n":1},"copied":["x",5],"blank":["x",5],"fetched":["x",5]}',
    )->and(json_encode($resource()->flip()->copy()->resolve(request())))->toBe(
        '{"source":{"1":5,"0":"x","n":2},"copied":[5,"x"],"blank":[5,"x"],"fetched":[5,"x"]}',
    )->and(json_encode($resource()->flip()->recount()->copy()->resolve(request())))->toBe(
        '{"source":{"1":5,"0":"x","n":3},"copied":[5,"x"],"blank":[5,"x"],"fetched":[5,"x"]}',
    );
});

// A plain class sends a list only where the keys run 0, 1, …, as keys from 5 on, or a named key always held, never do.
it('keeps the numbered keys of an untyped array a plain class can never send as a list', function () {
    $payload = fn (): NumberedTagsPayload => new NumberedTagsPayload;

    expect(analyzedKeyTypes(NumberedTagsPayload::class))->toBe([
        'fived' => '{ "5": string; k?: number }',
        'numbered' => '{ "1": string; "2": number; "7"?: boolean }',
        'rows' => '{ "1": { x: number; y?: number }; "2": { x: number } }',
        'named' => '{ a?: number; b?: string | number; [key: string]: number | string | undefined }',
        'swapped' => '{ a?: number; "5"?: string | number; [key: string]: number | string | undefined }',
        'merged' => '{ a?: number; "5"?: string; k?: number }',
        'pair' => '{ a?: number; "1"?: string; "2"?: number; k?: boolean }',
    ])->and(json_encode($payload()->toArray()))->toBe(
        '{"fived":{"5":"s"},"numbered":{"1":"a","2":5},"rows":{"1":{"x":1},"2":{"x":2}},"named":{"a":1},"swapped":{"a":1},'
        .'"merged":{"a":1},"pair":{"a":1}}',
    )->and(json_encode($payload()->keyFived()->keySeven()->extendRow()->swapNamed()->keyNamed('0')->swap()->keySwapped('0')
        ->swapMerged()->keyMerged()->swapPair()->keyPair()->toArray()))->toBe(
            '{"fived":{"5":"s","k":1},"numbered":{"1":"a","2":5,"7":true},"rows":{"1":{"x":1,"y":2},"2":{"x":2}},'
            .'"named":{"b":"x","0":1},"swapped":{"5":"s","0":1},"merged":{"5":"s","k":1},"pair":{"1":"x","2":5,"k":true}}',
        )->and(json_encode($payload()->keyNamed('0')->keySwapped('x')->keyMerged()->swapPair()->toArray()))->toBe(
            '{"fived":{"5":"s"},"numbered":{"1":"a","2":5},"rows":{"1":{"x":1},"2":{"x":2}},"named":{"a":1,"0":1},'
            .'"swapped":{"a":1,"x":1},"merged":{"a":1,"k":1},"pair":{"1":"x","2":5}}',
        );
});

// R1: after a whole unset() PHP reads the property through `__get()`, and a read there warns, so only the fresh value.
it('leaves `unknown` an untyped array the class unsets whole', function () {
    expect(analyzedKeyTypes(AppendedTagsPayload::class)['gone'])->toBe('unknown')
        ->and(json_encode(new AppendedTagsPayload()->toArray()['gone']))->toBe('["g"]');
});

it('publishes a workbench resource whose subclass appends to an object, beside a key write and under a computed key', function () {
    expect(analyzedKeyTypes(TagBoardResource::class))->toBe([
        'labels' => '{ a: string } & Record<number, string>',
        'both' => 'string[] | ({ k?: number } & Record<number, string>)',
        'dynamic' => '{ a: number; [key: string]: number }',
    ])->and(json_encode((new TagBoardResource(null))->resolve(request())))
        ->toBe('{"labels":{"a":"x"},"both":["a"],"dynamic":{"a":1}}')
        ->and(json_encode((new TagBoardResource(null))->tag('x')->resolve(request())))
        ->toBe('{"labels":{"a":"x","0":"x"},"both":{"0":"a","1":"x","k":1},"dynamic":{"a":1,"x":1}}');
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

// An instance method named through a class runs on `$this` where the object is one, so `static` may be a subclass.
it('assumes the class an instance method named through a class runs as, since the object may be a further subclass', function () {
    expect(analyzedKeyTypes(NamedPickHolder::class))->toBe(['v' => '{ label: unknown }'])
        ->and(json_encode((new NamedPickHolder)->toArray()))->toBe('{"v":{"label":true}}');
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
    $runtime = fn (bool $assumes): ?RuntimeClass => $assumes ? RuntimeClass::assumed(new ReflectionClass(SevenHelperLevelResource::class)) : null;
    $types = fn (bool $assumes): array => array_column(resolve(AstEngine::class)
        ->analyzeMethod(SevenHelperLevelResource::class, 'toArray', Post::class, runtime: $runtime($assumes))->properties, 'type', 'name');

    expect($types(true))->toMatchArray(['via_protected' => 'unknown', 'via_private' => 'unknown', 'const_self' => 'number'])
        ->and($types(false))->toMatchArray(['via_protected' => 'string | number', 'via_private' => 'string | number', 'const_self' => 'number']);
});

it('reads a spread response as assumed only where it is the class `static` names in an assumed scope', function () {
    $runtime = fn (bool $assumes): ?RuntimeClass => $assumes ? RuntimeClass::assumed(new ReflectionClass(SevenHelperLevelResource::class)) : null;
    $type = fn (string $method, bool $assumes): string => array_column(resolve(AstEngine::class)
        ->analyzeMethod(SevenHelperLevelResource::class, $method, Post::class, runtime: $runtime($assumes))->properties, 'type', 'name')['via_private'];

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

it('spreads parent::m() as the declaring class\'s parent runs it, in a value object and in a resource', function () {
    expect(analyzedKeyTypes(SpreadingChild::class))->toBe(['g' => 'number', 'b' => 'boolean', 'c' => 'string', 'sg' => 'number'])
        ->and(json_encode(new SpreadingChild()->toArray()))->toBe('{"g":1,"b":true,"c":"x","sg":1}')
        ->and(publishedResourceKeyTypes(SpreadingChildResource::class, Post::class))->toBe(['g' => 'number', 'ge' => 'number', 'b' => 'boolean'])
        ->and(json_encode((new SpreadingChildResource(null))->resolve(request())))->toBe('{"g":1,"ge":1,"b":true}');
});

it('spreads no key from a parent method PHP cannot call through parent::', function () {
    expect(analyzedKeyTypes(PrivateSpreadBase::class))->toBe(['k' => 'number'])
        ->and(fn () => new PrivateSpreadBase()->toArray())->toThrow(Error::class);
});

it('reads no key from an abstract parent method or a Laravel trait\'s through parent::, in a helper read as a value', function () {
    expect(analyzedKeyTypes(AbstractSpreadBase::class))->toBe(['v' => '{ k: number }'])
        ->and(fn () => new AbstractSpreadBase()->toArray())->toThrow(Error::class)
        ->and(analyzedKeyTypes(SerializingSpreadChild::class))->toBe(['v' => '{ k: number }'])
        ->and(json_encode(new SerializingSpreadChild()->toArray()))->toBe('{"v":{"k":1}}');
});

// The release read no such spread, so one read only partly falls back to that reading rather than publish its
// unread keys, or the first return's keys alone, which PHP sends as `"x"` once `$on` is false.
it('reads a parent:: spread a helper reads only partly as the release did, in a value', function () {
    $off = new PartlyReadSpreadPayload;
    $off->on = false;

    expect(analyzedKeyTypes(PartlyReadSpreadPayload::class))->toBe([
        'merged' => '{ k: number }', 'picked' => '{ k: number }', 'first' => '{ k: number }',
    ])->and(json_encode(new PartlyReadSpreadPayload()->toArray()))
        ->toBe('{"merged":{"k":1,"a":1,"b":2},"picked":{"k":1,"a":1},"first":{"k":1,"a":1}}')
        ->and(json_encode($off->toArray()))
        ->toBe('{"merged":{"k":1,"a":1,"b":2},"picked":{"k":1,"a":"x"},"first":{"k":1,"a":"x","c":true}}');
});

it('ends a parent:: spread that reaches back into a method it is reading', function () {
    expect(analyzedKeyTypesWithin(10, CyclingSpreadBase::class))->toBe(['b?' => 'number', 'g' => 'number'])
        ->and(json_encode(new CyclingSpreadBase()->toArray()))->toBe('{"g":1,"b":1}');
});

// A spread no arm reads may overwrite an earlier key, as `array_merge()` overwrites `a` here, so a `parent::` spread
// holding one is not read at all, as in the release, rather than publish the earlier key's type.
it('reads no key from a parent:: spread whose body holds a spread no arm reads', function () {
    expect(analyzedKeyTypes(OverwrittenSpreadTop::class))->toBe([])
        ->and(json_encode(new OverwrittenSpreadTop()->toArray()))->toBe('{"a":"x"}')
        ->and(analyzedKeyTypes(OverwrittenSpreadNested::class))->toBe([])
        ->and(json_encode(new OverwrittenSpreadNested()->toArray()))->toBe('{"a":"x"}')
        ->and(analyzedKeyTypes(OverwrittenSpreadChained::class))->toBe([])
        ->and(json_encode(new OverwrittenSpreadChained()->toArray()))->toBe('{"a":"x"}')
        ->and(analyzedKeyTypes(OverwrittenSpreadDelegated::class))->toBe([])
        ->and(json_encode(new OverwrittenSpreadDelegated()->toArray()))->toBe('{"a":"x","e":1}')
        ->and(analyzedKeyTypes(OverwrittenSpreadValue::class))->toBe(['v' => '{ k: number }'])
        ->and(json_encode(new OverwrittenSpreadValue()->toArray()))->toBe('{"v":{"k":1,"a":"x"}}')
        ->and(analyzedKeyTypes(OverwrittenSpreadNestedValue::class))->toBe(['v' => '{ k: number }'])
        ->and(json_encode(new OverwrittenSpreadNestedValue()->toArray()))->toBe('{"v":{"k":1,"a":"x"}}');
});

// The decline holds wherever the overwrite sits: a nested array, an argument the body binds, or a helper it reads as a
// value, each of which the spread's own analysis never sees.
it('reads no key from a parent:: spread whose read meets a spread no arm reads at any depth', function () {
    expect(analyzedKeyTypes(OverwrittenSpreadLiteral::class))->toBe([])
        ->and(json_encode(new OverwrittenSpreadLiteral()->toArray()))->toBe('{"v":{"k":"x"},"b":true}')
        ->and(analyzedKeyTypes(OverwrittenSpreadDeeper::class))->toBe([])
        ->and(json_encode(new OverwrittenSpreadDeeper()->toArray()))->toBe('{"v":{"w":{"k":"x"}},"b":true}')
        ->and(analyzedKeyTypes(OverwrittenSpreadArgument::class))->toBe([])
        ->and(json_encode(new OverwrittenSpreadArgument()->toArray()))->toBe('{"v":{"k":"x"},"b":true}')
        ->and(analyzedKeyTypes(OverwrittenSpreadHelper::class))->toBe([])
        ->and(json_encode(new OverwrittenSpreadHelper()->toArray()))->toBe('{"w":{"a":"x"},"b":true}');
});

it('adds no key from a declined parent:: spread that re-entered its cycle, rather than re-read it without end', function () {
    expect(analyzedKeyTypesWithin(10, CyclingOverwriteTop::class))->toBe([])
        ->and(json_encode(new CyclingOverwriteTop()->toArray()))->toBe('{"g":"x","b":1}')
        ->and(analyzedKeyTypesWithin(10, CyclingOverwriteValue::class))->toBe(['v' => '{ k: number }'])
        ->and(json_encode(new CyclingOverwriteValue()->toArray()))->toBe('{"v":{"k":1,"g":"x","b":1}}');
});
