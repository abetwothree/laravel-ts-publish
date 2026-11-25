<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Ops\JoinOptions;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Ops\ShapeJoin;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Ops\ShapeWrites;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Print\ShapeLowering;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Read\ArrayShapes;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ArrayShape;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\KeyPresence;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\KeyValue;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\OpaqueKind;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\OpaquePart;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeKey;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeSignature;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeWide;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\BranchJoin;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\IndexSignatureKey;
use AbeTwoThree\LaravelTsPublish\Tests\Support\RoundTripShapeObserver;
use PhpParser\Node\Expr\Variable;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * Each value a write may hold, by name: every channel addProperty() routes, the imports and classes beside them.
 *
 * @return array<string, array<string, mixed>>
 */
function shapeWriteValues(): array
{
    return [
        'enum' => ['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => Status::class],
        'resource' => ['type' => 'PostResource', 'optional' => false, 'resourceFqcn' => PostResource::class],
        'model' => ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class],
        'embedded' => [
            'type' => '{ user: User; state: StatusType; crm: CrmStatusType }',
            'optional' => true,
            'absent' => true,
            'embeddedModelFqcns' => [User::class],
            'embeddedEnumFqcns' => [Status::class, CrmStatus::class],
        ],
        'wrapped' => [
            'type' => '{ state: StatusType; post: PostResource }',
            'optional' => false,
            'embeddedResourceFqcns' => [PostResource::class],
            'wrappedEnumFqcns' => [Status::class],
            'embeddedEnumResourceFqcns' => [Status::class],
            'alikeClassFqcns' => [Post::class],
        ],
        'held' => ['type' => 'Post | null', 'optional' => false, 'heldObject' => true],
        'closed' => ['type' => '{ a: string }', 'optional' => false, 'closedToDottedKeys' => '{ a: string }'],
        'imported' => [
            'type' => 'Extra',
            'optional' => false,
            'customImports' => ['@/types' => ['Extra']],
            'carriedFqcns' => ['models' => [User::class]],
        ],
        'removed' => ['type' => 'number', 'optional' => true],
        'plain' => ['type' => 'string', 'optional' => false],
    ];
}

/**
 * The names a write may land under: named keys, a numeric one PHP stores as an int, and a signature.
 *
 * @return list<string>
 */
function shapeWriteNames(): array
{
    return ['a', 'b', '0', '[key: string]'];
}

/**
 * An analysis of the given keys, each `name => value name`, a signature added as one more entry, with the channels a
 * cast, a declaration or a dotted-key close sets beside them where asked.
 *
 * @param  array<string, string>  $keys
 */
function shapeWriteAnalysis(array $keys, bool $declared = false): MethodAnalysis
{
    $analysis = new MethodAnalysis;
    $values = shapeWriteValues();

    foreach ($keys as $name => $value) {
        $name = (string) $name;
        IndexSignatureKey::is($name)
            ? $analysis->addSignatureEntry($name, $values[$value])
            : $analysis->addProperty($name, $values[$value]);

        if ($declared) {
            $analysis->casts[$name] = ['type' => 'string', 'import' => null];
            $analysis->declaredTokenFqcns[$name] = [Status::class];
            $analysis->closedToDottedKeys[$name] = true;
        }
    }

    return $analysis;
}

/**
 * A shape lowered into a new analysis.
 */
function shapeWriteLowered(ArrayShape $shape): MethodAnalysis
{
    $analysis = new MethodAnalysis;
    ShapeLowering::into($analysis, $shape);

    return $analysis;
}

/**
 * A key write as the returned-variable walk landed it on an analysis: the key's channels forgotten, the value added,
 * the presence laid on its row, and a key held before kept in its first position.
 *
 * @param  array<string, mixed>  $value
 */
function shapeWriteKeyInto(MethodAnalysis $into, string $name, array $value, bool $optional, KeyPresence $presence): void
{
    $held = array_search($name, array_column($into->properties, 'name'), true);
    $into->forgetChannels($name);
    $appended = count($into->properties);
    $into->addProperty($name, $value, $optional);
    $into->properties[$held === false ? $appended : $held] = $presence->onRow($into->properties[$appended]);

    if ($held !== false) {
        array_splice($into->properties, $appended, 1);
    }
}

/**
 * A whole write as the returned-variable walk merged it into an analysis: each key held before keeps its position and
 * forgets its channels, each row takes its presence, then merge() brings the write's maps.
 */
function shapeWriteMergeInto(MethodAnalysis $into, MethodAnalysis $write, bool $conditional): void
{
    $properties = $into->properties;
    $names = array_column($properties, 'name');

    foreach ($write->properties as $row) {
        $at = IndexSignatureKey::is($row['name']) ? false : array_search($row['name'], $names, true);
        $held = $at === false ? KeyPresence::missing() : KeyPresence::ofRow($properties[$at]);
        $laid = KeyPresence::ofRow($row)->after($held, $conditional)->onRow($row);

        if ($at === false) {
            $properties[] = $laid;

            continue;
        }

        $into->forgetChannels($row['name']);
        $properties[$at] = $laid;
    }

    $into->properties = [];
    $into->merge($write);
    $into->properties = $properties;
}

/**
 * A lifted shape of the given keys with typed extras or a part bringing unread keys beside them.
 *
 * @param  array<string, string>  $keys
 */
function shapeWriteUnread(array $keys, bool $extras, bool $opaque, OpaqueKind $kind = OpaqueKind::UnreadWholeWrite): ArrayShape
{
    $lifted = ArrayShapes::ofAnalysis(shapeWriteAnalysis($keys));

    return new ArrayShape(
        $lifted->entries,
        $extras ? new KeyValue(['type' => 'number', 'optional' => false, 'description' => '']) : null,
        $opaque ? [new OpaquePart(new Variable('rest'), $kind)] : [],
        false,
        $lifted->wide,
    );
}

/**
 * Apply one write to a shape through ShapeWrites and to an analysis as MethodAnalysis writes it, from a random draw;
 * a join joins the shape with a copy given one more write, as BranchJoin joins the two analyses.
 *
 * @return array{ArrayShape, string}
 */
function shapeWriteStep(ArrayShape $shape, MethodAnalysis $analysis, Randomizer $random, bool $joins = true): array
{
    $values = shapeWriteValues();
    $value = $random->pickArrayKeys($values, 1)[0];
    $name = shapeWriteNames()[$random->getInt(0, 3)];
    $conditional = $random->getInt(0, 1) === 1;

    switch ($random->getInt(0, $joins ? 7 : 6)) {
        case 0:
        case 1:
            if (IndexSignatureKey::is($name)) {
                $analysis->addSignatureEntry($name, $values[$value]);

                return [ShapeWrites::signatureWritten($shape, $name, $values[$value]), "signature {$value}"];
            }

            $presence = new KeyPresence($conditional, $conditional, $values[$value]['optional']);
            shapeWriteKeyInto($analysis, $name, $values[$value], $conditional, $presence);

            return [ShapeWrites::keyWritten($shape, $name, $values[$value], $conditional, $presence), "key {$name} {$value}"];
        case 2:
        case 3:
            $keys = [];

            foreach ($random->pickArrayKeys(array_flip(shapeWriteNames()), $random->getInt(1, 3)) as $key) {
                $keys[(string) $key] = (string) $random->pickArrayKeys($values, 1)[0];
            }

            $write = shapeWriteAnalysis($keys, $random->getInt(0, 1) === 1);
            // A map entry no row of the write names, which merge() lays under that name all the same.
            $stray = shapeWriteNames()[$random->getInt(0, 3)];
            $write->casts[$stray] ??= ['type' => 'number', 'import' => null];
            $write->declaredTokenFqcns[$stray] ??= [Post::class];
            $write->alikeClassFqcns[$stray] ??= [Post::class];
            $write->inlineModelFqcns[$stray] ??= [User::class];
            $lifted = ArrayShapes::ofAnalysis($write);
            shapeWriteMergeInto($analysis, $write, $conditional);

            return [ShapeWrites::merged($shape, $lifted, $conditional), 'merge '.json_encode($keys)];
        case 4:
            $write = shapeWriteAnalysis([$name => $value, 'b' => 'plain']);
            $held = array_column($analysis->properties, 'name');
            $lifted = ShapeWrites::withoutKeys(ArrayShapes::ofAnalysis($write), $held);
            $write->dropKeys($held);
            shapeWriteMergeInto($analysis, $write, $conditional);

            return [ShapeWrites::merged($shape, $lifted, $conditional), "plus {$name} {$value}"];
        case 5:
            $analysis->forgetChannels($name);
            $analysis->properties = array_values(array_filter(
                $analysis->properties,
                static fn (array $row): bool => $row['name'] !== $name,
            ));

            return [ShapeWrites::without($shape, $name), "unset {$name}"];
        case 6:
            $analysis->forgetChannels($name);

            return [ShapeWrites::withoutChannels($shape, $name), "forget {$name}"];
        default:
            $other = clone $analysis;
            [$branch, $written] = shapeWriteStep($shape, $other, $random, joins: false);
            $joined = new MethodAnalysis;
            ShapeLowering::into($joined, ShapeJoin::exclusive(
                [$shape, $branch],
                new JoinOptions(dropsUntypedBranches: true, keepsLoneNull: true),
            ));
            $expected = new MethodAnalysis;
            BranchJoin::joinInto($expected, [clone $analysis, $other], dropsUntypedBranches: true, keepsLoneNull: true);
            $analysis->replaceWith($expected);

            return [ArrayShapes::ofAnalysis($joined), "join ({$written})"];
    }
}

it('keeps a re-assigned key\'s held object through a whole write, as merge() brings none', function () {
    $held = ArrayShapes::ofAnalysis(shapeWriteAnalysis(['a' => 'held']));
    $merged = ShapeWrites::merged(ShapeWrites::seeded($held), ArrayShapes::ofAnalysis(shapeWriteAnalysis(['a' => 'held'])), false);
    $written = ShapeWrites::keyWritten(new ArrayShape, 'a', shapeWriteValues()['held'], false, new KeyPresence);
    $rewritten = ShapeWrites::merged($written, ArrayShapes::ofAnalysis(shapeWriteAnalysis(['a' => 'plain'])), false);

    expect(shapeWriteLowered($merged)->heldObjectKeys)->toBe([])
        ->and(shapeWriteLowered($rewritten)->heldObjectKeys)->toBe(['a' => ['objectNull' => true, 'plainNull' => false]]);
});

it('appends each signature entry\'s queues under its one pattern, every entry holding them', function () {
    $shape = ShapeWrites::signatureWritten(new ArrayShape, '[key: string]', shapeWriteValues()['embedded']);
    $shape = ShapeWrites::signatureWritten($shape, '[key: string]', shapeWriteValues()['embedded']);

    expect(shapeWriteLowered($shape)->inlineEnumFqcns)
        ->toBe(['[key: string]' => [Status::class, CrmStatus::class, Status::class, CrmStatus::class]])
        ->and($shape->entries[0]->value->channels)->toBe($shape->entries[1]->value->channels);
});

it('keeps an overwritten key\'s embedded classes and custom imports, and moves it to the end of each map it clears', function () {
    $shape = ShapeWrites::keyWritten(new ArrayShape, 'a', shapeWriteValues()['embedded'], false, new KeyPresence);
    $shape = ShapeWrites::keyWritten($shape, 'b', shapeWriteValues()['model'], false, new KeyPresence);
    $shape = ShapeWrites::keyWritten($shape, 'a', shapeWriteValues()['imported'], false, new KeyPresence);
    $shape = ShapeWrites::keyWritten($shape, 'a', shapeWriteValues()['model'], false, new KeyPresence);
    $lowered = shapeWriteLowered($shape);

    expect(array_column($lowered->properties, 'name'))->toBe(['a', 'b'])
        ->and($lowered->modelFqcns)->toBe([User::class => User::class, 'b' => User::class, 'a' => User::class])
        ->and($lowered->directEnumFqcns)->toBe([Status::class => Status::class, CrmStatus::class => CrmStatus::class])
        ->and($lowered->customImports)->toBe(['@/types' => ['Extra']]);
});

it('seeds a shape as merge() lays it into an empty analysis', function () {
    $seed = shapeWriteAnalysis(['a' => 'held', 'b' => 'embedded', '[key: string]' => 'wrapped'], declared: true);
    $seed->flatTypeAlias = 'Posts';
    $expected = new MethodAnalysis;
    $expected->merge($seed);
    $seeded = shapeWriteLowered(ShapeWrites::seeded(ArrayShapes::ofAnalysis($seed)));

    expect(fn () => RoundTripShapeObserver::assertSameFields($expected, $seeded))->not->toThrow(LogicException::class)
        ->and($seeded->heldObjectKeys)->toBe([])
        ->and($seeded->flatTypeAlias)->toBeNull();
});

it('lands every write sequence as MethodAnalysis lands it, each map\'s order included', function (int $seed) {
    $random = new Randomizer(new Xoshiro256StarStar($seed));
    $analysis = shapeWriteAnalysis(['a' => 'embedded', 'b' => 'held']);
    $lifted = ArrayShapes::ofAnalysis($analysis);
    // Only the entries hold the classes their queues key by themselves, so a write must keep them once a key goes.
    $shape = new ArrayShape($lifted->entries, wide: new ShapeWide($lifted->wide->customImports, order: $lifted->wide->order));
    $steps = [];

    for ($step = 0; $step < 12; $step++) {
        [$shape, $steps[]] = shapeWriteStep($shape, $analysis, $random);

        try {
            RoundTripShapeObserver::assertSameFields($analysis, shapeWriteLowered($shape));
        } catch (LogicException $failure) {
            throw new LogicException(implode(', ', $steps).":\n".$failure->getMessage());
        }
    }

    expect($steps)->toHaveCount(12);
})->with(range(1, 80));

it('reads a seed\'s, a whole write\'s or a += value\'s extras and opaque parts as the unread keys it lowers to', function (string $write, bool $extras, bool $opaque) {
    $input = shapeWriteUnread(['a' => 'plain'], $extras, $opaque);
    $held = ShapeWrites::keyWritten(new ArrayShape, 'b', shapeWriteValues()['plain'], false, new KeyPresence);
    $lowered = shapeWriteLowered($input);
    $expected = shapeWriteLowered($held);

    if ($write === 'seed') {
        $expected = new MethodAnalysis;
        $expected->merge($lowered);
        $written = ShapeWrites::seeded($input);
    } elseif ($write === 'whole write') {
        shapeWriteMergeInto($expected, $lowered, false);
        $written = ShapeWrites::merged($held, $input, false);
    } else {
        $lowered->dropKeys(['b']);
        shapeWriteMergeInto($expected, $lowered, false);
        $written = ShapeWrites::merged($held, ShapeWrites::withoutKeys($input, ['b']), false);
    }

    expect(fn () => RoundTripShapeObserver::assertSameFields($expected, shapeWriteLowered($written)))
        ->not->toThrow(LogicException::class)
        ->and([$written->wide->holdsUnreadKeys, $written->wide->readsCompletely])->toBe([true, ! $opaque]);
})->with(['seed', 'whole write', '+= value'])->with([
    'typed extras' => [true, false],
    'an opaque part' => [false, true],
]);

it('refuses an opaque part no rule lowers yet, as the lowering does', function () {
    $spread = shapeWriteUnread(['a' => 'plain'], false, true, OpaqueKind::UnreadSpread);

    expect(fn () => ShapeWrites::seeded($spread))->toThrow(LogicException::class, 'kind UnreadSpread')
        ->and(fn () => ShapeWrites::merged(new ArrayShape, $spread, false))->toThrow(LogicException::class, 'kind UnreadSpread');
});

it('keeps a written shape\'s extras and opaque parts, and no never-empty proof', function (Closure $write) {
    $lifted = ArrayShapes::ofAnalysis(shapeWriteAnalysis(['a' => 'plain', '[key: string]' => 'plain']));
    $extras = new KeyValue(['type' => 'number', 'optional' => false, 'description' => '']);
    $part = new OpaquePart(new Variable('rest'), OpaqueKind::UnnamedKeyWrite);
    $written = $write(new ArrayShape($lifted->entries, $extras, [$part], true, $lifted->wide));

    expect($written->extras)->toBe($extras)
        ->and($written->opaque)->toBe([$part])
        ->and($written->nonEmptyProof)->toBeFalse();
})->with([
    'a key write' => [fn (ArrayShape $shape) => ShapeWrites::keyWritten($shape, 'b', shapeWriteValues()['plain'], false, new KeyPresence)],
    'a signature entry' => [fn (ArrayShape $shape) => ShapeWrites::signatureWritten($shape, '[key: string]', shapeWriteValues()['plain'])],
    'a whole write' => [fn (ArrayShape $shape) => ShapeWrites::merged($shape, shapeWriteUnread(['b' => 'plain'], false, false), false)],
    'an unset()' => [fn (ArrayShape $shape) => ShapeWrites::without($shape, 'a')],
    'a forgotten key' => [fn (ArrayShape $shape) => ShapeWrites::withoutChannels($shape, 'a')],
    'a key dropped' => [fn (ArrayShape $shape) => ShapeWrites::withoutKeys($shape, ['a'])],
    'an entry mapped' => [fn (ArrayShape $shape) => ShapeWrites::mapped(
        $shape,
        'a',
        static fn (ShapeKey|ShapeSignature $entry): ShapeKey|ShapeSignature => $entry,
    )],
]);

it('keeps the never-empty proof where only the read flags change', function () {
    $proven = new ArrayShape(nonEmptyProof: true);

    expect(ShapeWrites::withFlags($proven, readsCompletely: false)->nonEmptyProof)->toBeTrue();
});
