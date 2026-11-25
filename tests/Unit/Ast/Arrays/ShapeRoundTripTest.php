<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Fold\LiteralWrites;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Print\ShapeLowering;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Read\ArrayShapes;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ArrayShape;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\KeyPresence;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\KeyValue;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeKey;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeObserver;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\LiteralVariants;
use AbeTwoThree\LaravelTsPublish\Tests\Support\RoundTripShapeObserver;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * An analysis holding every field the round trip rebuilds: rows with each value field and presence, a signature,
 * every channel under a key's name, classes an embedded queue keys by themselves, and the analysis-wide data.
 */
function roundTripAnalysis(): ResourceAnalysis
{
    $analysis = new ResourceAnalysis;
    $analysis->addProperty('status', ['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => Status::class]);
    $analysis->addProperty('author', [
        'type' => '{ user: User }', 'optional' => true, 'absent' => true, 'embeddedModelFqcns' => [User::class],
    ]);
    $analysis->addProperty('post', ['type' => 'PostResource', 'optional' => true, 'resourceFqcn' => PostResource::class]);
    $analysis->addProperty('owner', ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class]);
    $analysis->addProperty('badge', [
        'type' => '{ state: StatusType; post: PostResource }',
        'optional' => false,
        'embeddedEnumFqcns' => [Status::class],
        'embeddedResourceFqcns' => [PostResource::class],
        'wrappedEnumFqcns' => [Status::class],
    ]);
    $analysis->addSignatureEntry('[key: string]', ['type' => 'number', 'optional' => false]);
    $analysis->queueEmbeddedClasses('author', [Post::class]);
    $analysis->inlineEnumResourceFqcns['badge'] = [Status::class];
    $analysis->alikeClassFqcns['badge'] = [Post::class];
    $analysis->casts['status'] = ['type' => 'string', 'import' => null, 'optional' => true];
    $analysis->declaredTokenFqcns['status'] = [Status::class];
    $analysis->heldObjectKeys['post'] = ['objectNull' => true, 'plainNull' => false];
    $analysis->closedToDottedKeys['author'] = true;
    $analysis->customImports['@/types'] = ['Extra'];
    $analysis->carry(['models' => [User::class]]);
    $analysis->holdUnreadKeys();
    $analysis->holdsUnnamedKeys = true;
    $analysis->flatTypeAlias = 'Posts';
    $analysis->flatTypeAliasFqcn = PostResource::class;
    $analysis->variants = new LiteralVariants([], new LiteralWrites, false, [], []);
    $analysis->setSpreadArms([['fqcn' => PostResource::class, 'isModel' => false, 'isCollection' => false]]);
    $analysis->properties[1]['bodyType'] = '{ user: User } | null';
    $analysis->properties[1]['keepsKeys'] = true;

    return $analysis;
}

/**
 * One change per field an analysis holds, each beside the field it changes.
 *
 * @return array<string, array{string, Closure(MethodAnalysis): mixed}>
 */
function roundTripChanges(): array
{
    return [
        'a row' => ['properties', fn (MethodAnalysis $a) => $a->properties[0]['type'] = 'string'],
        'a row\'s presence' => ['properties', fn (MethodAnalysis $a) => $a->properties[1] = MethodAnalysis::withPresence($a->properties[1], true, false, true)],
        'the row order' => ['properties', fn (MethodAnalysis $a) => $a->properties = array_reverse($a->properties)],
        'a resource channel' => ['nestedResources', fn (MethodAnalysis $a) => $a->nestedResources = []],
        'the custom imports' => ['customImports', fn (MethodAnalysis $a) => $a->customImports = []],
        'an enum channel\'s order' => ['directEnumFqcns', fn (MethodAnalysis $a) => $a->directEnumFqcns = array_reverse($a->directEnumFqcns, true)],
        'a model channel' => ['modelFqcns', fn (MethodAnalysis $a) => $a->modelFqcns['x'] = User::class],
        'an inline enum queue' => ['inlineEnumFqcns', fn (MethodAnalysis $a) => $a->inlineEnumFqcns = []],
        'an inline model queue' => ['inlineModelFqcns', fn (MethodAnalysis $a) => $a->inlineModelFqcns = []],
        'an inline resource queue' => ['inlineResourceFqcns', fn (MethodAnalysis $a) => $a->inlineResourceFqcns = []],
        'an enum wrap\'s queue' => ['inlineEnumResourceFqcns', fn (MethodAnalysis $a) => $a->inlineEnumResourceFqcns = []],
        'the wrapped enums' => ['wrappedEnums', fn (MethodAnalysis $a) => $a->wrappedEnums = []],
        'a cast' => ['casts', fn (MethodAnalysis $a) => $a->casts = []],
        'the carried classes' => ['carried', fn (MethodAnalysis $a) => $a->carried = []],
        'the flat type alias' => ['flatTypeAlias', fn (MethodAnalysis $a) => $a->flatTypeAlias = null],
        'the flat type alias\'s class' => ['flatTypeAliasFqcn', fn (MethodAnalysis $a) => $a->flatTypeAliasFqcn = null],
        'the complete read' => ['readsCompletely', fn (MethodAnalysis $a) => $a->readsCompletely = true],
        'the unread keys flag' => ['holdsUnreadKeys', fn (MethodAnalysis $a) => $a->holdsUnreadKeys = false],
        'the unnamed keys flag' => ['holdsUnnamedKeys', fn (MethodAnalysis $a) => $a->holdsUnnamedKeys = false],
        'declared slots' => ['declaredTokenFqcns', fn (MethodAnalysis $a) => $a->declaredTokenFqcns = []],
        'an alike class queue' => ['alikeClassFqcns', fn (MethodAnalysis $a) => $a->alikeClassFqcns = []],
        'a held object' => ['heldObjectKeys', fn (MethodAnalysis $a) => $a->heldObjectKeys = []],
        'a dotted-key close' => ['closedToDottedKeys', fn (MethodAnalysis $a) => $a->closedToDottedKeys = []],
        'the variants' => ['variants', fn (MethodAnalysis $a) => $a->variants = null],
        'the spread arms' => ['spreadArms', fn (MethodAnalysis $a) => $a->setSpreadArms([])],
    ];
}

it('runs every suite read under the round-trip guard', function () {
    expect(resolve(ShapeObserver::class))->toBeInstanceOf(RoundTripShapeObserver::class);
});

it('lifts an analysis into a shape and lowers it back with every field rebuilt', function () {
    $analysis = roundTripAnalysis();
    $rebuilt = new ResourceAnalysis;
    ShapeLowering::into($rebuilt, ArrayShapes::ofAnalysis($analysis));

    expect(fn () => RoundTripShapeObserver::assertSameFields($analysis, $rebuilt))->not->toThrow(LogicException::class);
});

it('throws on an analysis with one field changed, naming the field', function (string $field, Closure $change) {
    $analysis = roundTripAnalysis();
    $changed = clone $analysis;
    $change($changed);

    expect(fn () => RoundTripShapeObserver::assertSameFields($analysis, $changed))
        ->toThrow(LogicException::class, "The shape round trip changed `{$field}`:");
})->with(roundTripChanges());

it('changes every field an analysis holds in some row', function () {
    $fields = array_map(
        static fn (ReflectionProperty $field): string => $field->getName(),
        RoundTripShapeObserver::fields(ResourceAnalysis::class),
    );

    expect(array_values(array_unique(array_column(roundTripChanges(), 0))))->toEqualCanonicalizing($fields);
});

it('fails the test whose read made an analysis with a field the lift drops, though the read catches it', function () {
    $analysis = new class extends ResourceAnalysis
    {
        public bool $unlifted = false;
    };
    $analysis->unlifted = true;
    $observer = new RoundTripShapeObserver;

    expect(fn () => $observer->observed($analysis))->toThrow(LogicException::class, 'The shape round trip changed `unlifted`:')
        ->and(fn () => $observer->rethrowCaught())->toThrow(LogicException::class, 'The shape round trip changed `unlifted`:');
});

it('keeps no failure for an analysis that round-trips', function () {
    $observer = new RoundTripShapeObserver;
    $observer->observed(roundTripAnalysis());

    expect(fn () => $observer->rethrowCaught())->not->toThrow(LogicException::class);
});

it('lowers a shape\'s extras as unread keys, which no row can say', function () {
    $analysis = new ResourceAnalysis;
    $value = new KeyValue(['type' => 'number', 'optional' => false, 'description' => '']);
    ShapeLowering::into($analysis, new ArrayShape([new ShapeKey('id', $value, new KeyPresence)], new KeyValue([
        'type' => 'string', 'optional' => false, 'description' => '',
    ])));

    expect($analysis->properties)->toBe([['name' => 'id', 'type' => 'number', 'optional' => false, 'description' => '']])
        ->and($analysis->holdsUnreadKeys)->toBeTrue();
});

it('lowers an entry\'s presence over the one its value was read with', function () {
    $analysis = new ResourceAnalysis;
    $value = new KeyValue(['type' => 'number', 'optional' => false, 'description' => '']);
    ShapeLowering::into($analysis, new ArrayShape([new ShapeKey('id', $value, new KeyPresence(true, false, true))]));

    expect($analysis->properties)->toBe([['name' => 'id', 'type' => 'number', 'optional' => true, 'description' => '', 'removed' => true]]);
});
