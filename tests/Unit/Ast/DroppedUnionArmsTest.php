<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\SourceFileVisitor;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\GapModeKeysResource;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\NodeFinder;

/**
 * A scope analysing stdClass, the subject every warning below names.
 */
function droppedArmsScope(): AnalysisScope
{
    return new AnalysisScope(new ReflectionClass(stdClass::class));
}

/**
 * An arm on line 7, as the warnings below quote it.
 */
function droppedArm(string $name): Variable
{
    return new Variable($name, ['startLine' => 7]);
}

/**
 * A value of one type, as a site hands its union to an entry.
 *
 * @return array<string, mixed>
 */
function droppedArmsUnion(string $type): array
{
    return [...ValueResult::unknown(), 'type' => $type];
}

/**
 * The run-output message for an arm on line 7 whose drop left a placeholder.
 */
function droppedArmPlaceholderMessage(string $arm, string $published = '`null`, as if it were always null'): string
{
    return "The value on line 7 publishes {$published}, because `{$arm}` could not be typed and was left out of its union. "
        .'Give what it reads a return type or a `@return` docblock.';
}

/**
 * The run-output message for an arm on line 7 a join left out beside typed ones.
 */
function droppedArmLeftOutMessage(string $arm): string
{
    return "The value on line 7 leaves out `{$arm}`, which could not be typed, so its published type may not admit every "
        .'value it sends. Give what it reads a return type or a `@return` docblock.';
}

/**
 * The messages the run's warnings hold.
 *
 * @return list<string>
 */
function droppedArmsRunMessages(): array
{
    return array_column(AnalysisWarnings::all(), 'message');
}

/**
 * Make a gap for an arm left out of a `null` union and hold the placeholder warning its drop raises, by its id.
 */
function heldDroppedArmGap(Variable $arm): int
{
    $id = DroppedUnionArms::gap(droppedArmsScope(), 'match-arm', $arm, 'null');
    DroppedUnionArms::hold($id, droppedArmPlaceholderMessage('$'.$arm->name), $arm, stdClass::class);

    return $id;
}

/**
 * Run a read with gaps shown or hidden.
 */
function inGapMode(bool $shows, Closure $read): mixed
{
    $memo = resolve(AnalysisMemo::class);

    return $shows ? $memo->showingGaps($read) : $memo->hidingGaps($read);
}

dataset('gap modes', ['gaps hidden' => [false], 'gaps shown' => [true]]);

test('leftOut() records and counts each arm under its site, and warns of its placeholder, held by a gap in the mode', function (bool $shows) {
    [$first, $second] = [droppedArm('first'), droppedArm('second')];
    $union = droppedArmsUnion('null');
    $dropped = DroppedUnionArms::dropped();
    DroppedUnionArms::start();

    try {
        $returned = inGapMode($shows, fn (): array => DroppedUnionArms::leftOut(
            $union,
            [[$first, 'match-arm'], [$second, 'coalesce-right']],
            droppedArmsScope(),
        ));
    } finally {
        $audit = DroppedUnionArms::stop();
    }

    $held = droppedArmsRunMessages();
    DroppedUnionArms::stripped(VisibleGaps::ids($returned['type']));

    expect(VisibleGaps::stripped($returned['type'])[0])->toBe($union['type'])
        ->and(count(VisibleGaps::ids($returned['type'])))->toBe($shows ? 2 : 0)
        ->and(DroppedUnionArms::dropped() - $dropped)->toBe(2)
        ->and($audit)->toBe([
            ['subject' => stdClass::class, 'line' => 7, 'expression' => '$first', 'site' => 'match-arm'],
            ['subject' => stdClass::class, 'line' => 7, 'expression' => '$second', 'site' => 'coalesce-right'],
        ])
        ->and($held)->toBe($shows ? [] : droppedArmsRunMessages())
        ->and(droppedArmsRunMessages())->toBe([droppedArmPlaceholderMessage('$first'), droppedArmPlaceholderMessage('$second')]);
})->with('gap modes');

test('leftOut() warns of nothing where a typed arm stands, and records nothing with no arm left out', function (bool $shows) {
    $union = droppedArmsUnion('string | null');
    $dropped = DroppedUnionArms::dropped();
    DroppedUnionArms::start();

    try {
        $returned = inGapMode($shows, fn (): array => [
            DroppedUnionArms::leftOut($union, [[droppedArm('arm'), 'ternary-narrowed']], droppedArmsScope()),
            DroppedUnionArms::leftOut(droppedArmsUnion('null'), [], droppedArmsScope()),
        ]);
    } finally {
        $audit = DroppedUnionArms::stop();
    }

    expect(VisibleGaps::stripped($returned[0]['type'])[0])->toBe($union['type'])
        ->and(count(VisibleGaps::ids($returned[0]['type'])))->toBe($shows ? 1 : 0)
        ->and($returned[1])->toBe(droppedArmsUnion('null'))
        ->and(DroppedUnionArms::dropped() - $dropped)->toBe(1)
        ->and(array_column($audit, 'site'))->toBe(['ternary-narrowed'])
        ->and(droppedArmsRunMessages())->toBe([]);
})->with('gap modes');

test('leftOutOfJoin() records each arm and warns of a placeholder as leftOut() does, of a typed union as left out', function (bool $shows) {
    [$placeholder, $typed] = [droppedArm('placeholder'), droppedArm('typed')];
    $dropped = DroppedUnionArms::dropped();
    DroppedUnionArms::start();

    try {
        $returned = inGapMode($shows, fn (): array => [
            DroppedUnionArms::leftOutOfJoin(droppedArmsUnion('never[]'), [[$placeholder, 'walk-branch-join']], droppedArmsScope()),
            DroppedUnionArms::leftOutOfJoin(droppedArmsUnion('string'), [[$typed, 'binding-join']], droppedArmsScope()),
        ]);
    } finally {
        $audit = DroppedUnionArms::stop();
    }

    $held = droppedArmsRunMessages();
    DroppedUnionArms::stripped([...VisibleGaps::ids($returned[0]['type']), ...VisibleGaps::ids($returned[1]['type'])]);

    expect(array_map(static fn (array $union): string => VisibleGaps::stripped($union['type'])[0], $returned))->toBe(['never[]', 'string'])
        ->and(array_map(static fn (array $union): int => count(VisibleGaps::ids($union['type'])), $returned))->toBe($shows ? [1, 1] : [0, 0])
        ->and(DroppedUnionArms::dropped() - $dropped)->toBe(2)
        ->and(array_column($audit, 'site'))->toBe(['walk-branch-join', 'binding-join'])
        ->and($held)->toBe($shows ? [] : droppedArmsRunMessages())
        ->and(droppedArmsRunMessages())->toBe([
            droppedArmPlaceholderMessage('$placeholder', '`never[]`, an array that is always empty'),
            droppedArmLeftOutMessage('$typed'),
        ]);
})->with('gap modes');

test('one arm node, site, union and subject give one gap id for the run, and changing any gives another', function () {
    $scope = droppedArmsScope();
    [$arm, $other] = [new Variable('arm'), new Variable('other')];
    $dropped = DroppedUnionArms::dropped();
    $id = DroppedUnionArms::gap($scope, 'match-arm', $arm, 'string');

    expect(DroppedUnionArms::gap(droppedArmsScope(), 'match-arm', $arm, 'string'))->toBe($id)
        ->and(DroppedUnionArms::gap($scope, 'match-arm', $arm, 'string | $gap7'))->toBe($id)
        ->and(DroppedUnionArms::dropped())->toBe($dropped)
        ->and(array_unique([
            $id,
            DroppedUnionArms::gap($scope, 'match-arm', $other, 'string'),
            DroppedUnionArms::gap($scope, 'ternary-narrowed', $arm, 'string'),
            DroppedUnionArms::gap($scope, 'match-arm', $arm, 'string | null'),
            DroppedUnionArms::gap(new AnalysisScope(new ReflectionClass(ArrayObject::class)), 'match-arm', $arm, 'string'),
        ]))->toHaveCount(5);
});

test('an arm keeps its gap id when its file is parsed again, as once the parser cache has let the file go', function () {
    $path = (string) new ReflectionClass(GapModeKeysResource::class)->getFileName();
    $calls = static fn (AstParser $parser): array => new NodeFinder()->findInstanceOf($parser->parseFile($path), MethodCall::class);
    [$first, $second] = $calls(new AstParser);
    $again = $calls(new AstParser)[0];
    $scope = droppedArmsScope();
    $id = DroppedUnionArms::gap($scope, 'match-arm', $first, 'string');
    $elsewhere = new Variable('arm', [...$first->getAttributes(), SourceFileVisitor::ATTRIBUTE => $path.'.other']);

    expect($again)->not->toBe($first)
        ->and($again->getAttribute(SourceFileVisitor::ATTRIBUTE))->toBe($path)
        ->and(DroppedUnionArms::gap($scope, 'match-arm', $again, 'string'))->toBe($id)
        ->and(DroppedUnionArms::gap($scope, 'match-arm', $second, 'string'))->not->toBe($id)
        ->and(DroppedUnionArms::gap($scope, 'match-arm', $elsewhere, 'string'))->not->toBe($id);
});

test('the gap log gives each gap made or replayed since a mark, each once, and a replay counts its dropped arms', function () {
    $scope = droppedArmsScope();
    $arm = new Variable('arm');
    $before = DroppedUnionArms::gapMark();
    $id = DroppedUnionArms::gap($scope, 'match-arm', $arm, 'string');
    $mark = DroppedUnionArms::gapMark();
    DroppedUnionArms::gap($scope, 'match-arm', $arm, 'string');
    $dropped = DroppedUnionArms::dropped();
    DroppedUnionArms::replay(3, [$id + 40, $id]);

    expect(DroppedUnionArms::gapsSince($before))->toBe([$id, $id + 40])
        ->and(DroppedUnionArms::gapsSince($mark))->toBe([$id, $id + 40])
        ->and(DroppedUnionArms::gapsSince(DroppedUnionArms::gapMark()))->toBe([])
        ->and(DroppedUnionArms::dropped() - $dropped)->toBe(3);
});

test('a strip raises a held gap warning once, worded as the run words it', function () {
    $id = heldDroppedArmGap(droppedArm('arm'));
    $held = AnalysisWarnings::all();
    DroppedUnionArms::stripped([$id]);
    $raised = AnalysisWarnings::all();
    AnalysisWarnings::reset();
    DroppedUnionArms::stripped([$id]);

    expect($held)->toBe([])
        ->and($raised)->toBe([['subject' => stdClass::class, 'message' => droppedArmPlaceholderMessage('$arm')]])
        ->and(AnalysisWarnings::all())->toBe([]);
});

test('a gap filled under one key and stripped under another warns once, in either order', function (bool $fillsFirst) {
    $raised = DroppedUnionArms::extent(function () use ($fillsFirst): array {
        $id = heldDroppedArmGap(droppedArm('arm'));
        $fillsFirst ? DroppedUnionArms::filled([$id]) : DroppedUnionArms::stripped([$id]);
        $fillsFirst ? DroppedUnionArms::stripped([$id]) : DroppedUnionArms::filled([$id]);
        $raised = droppedArmsRunMessages();
        AnalysisWarnings::reset();

        return $raised;
    });

    expect($raised)->toBe([droppedArmPlaceholderMessage('$arm')])
        ->and(droppedArmsRunMessages())->toBe([]);
})->with(['filled first' => [true], 'stripped first' => [false]]);

test('an extent raises an unfilled, unstripped gap warning when it ends, a throw included, and skips a filled one', function () {
    $during = DroppedUnionArms::extent(function (): array {
        heldDroppedArmGap(droppedArm('open'));
        DroppedUnionArms::filled([heldDroppedArmGap(droppedArm('filled'))]);
        DroppedUnionArms::stripped([heldDroppedArmGap(droppedArm('stripped'))]);
        AnalysisWarnings::reset();

        return droppedArmsRunMessages();
    });
    $ended = droppedArmsRunMessages();
    AnalysisWarnings::reset();

    try {
        DroppedUnionArms::extent(function (): never {
            heldDroppedArmGap(droppedArm('thrown'));

            throw new RuntimeException('the read failed');
        });
    } catch (RuntimeException) {
    }

    expect($during)->toBe([])
        ->and($ended)->toBe([droppedArmPlaceholderMessage('$open')])
        ->and(droppedArmsRunMessages())->toBe([droppedArmPlaceholderMessage('$thrown')]);
});

test('an extent inside another leaves its gaps to the outermost, which a later fill still reaches', function () {
    $afterInner = DroppedUnionArms::extent(function (): array {
        $ids = DroppedUnionArms::extent(fn (): array => [heldDroppedArmGap(droppedArm('open')), heldDroppedArmGap(droppedArm('filled'))]);
        DroppedUnionArms::filled([$ids[1]]);

        return droppedArmsRunMessages();
    });

    expect($afterInner)->toBe([])
        ->and(droppedArmsRunMessages())->toBe([droppedArmPlaceholderMessage('$open')]);
});

test('a replaceable read settles the held gap warnings whose arm it rejects, and an outer one asks again of the rest', function () {
    [$kept, $replaced, $outer] = [droppedArm('kept'), droppedArm('replaced'), droppedArm('outer')];
    $ids = [];

    $value = DroppedUnionArms::replaceable(
        function () use ($kept, $replaced, $outer, &$ids): string {
            $ids = DroppedUnionArms::replaceable(
                fn (): array => [heldDroppedArmGap($kept), heldDroppedArmGap($replaced), heldDroppedArmGap($outer)],
                fn (array $read, ?Expr $arm): bool => $arm !== $replaced,
            );

            return 'read';
        },
        fn (string $read, ?Expr $arm): bool => $arm !== $outer,
    );
    $afterRead = droppedArmsRunMessages();
    DroppedUnionArms::stripped($ids);

    expect($value)->toBe('read')
        ->and($afterRead)->toBe([])
        ->and(droppedArmsRunMessages())->toBe([droppedArmPlaceholderMessage('$kept')]);
});

test('a replaceable read that throws keeps none of the gap warnings it held, as it keeps none it raised', function () {
    $ids = [];

    try {
        DroppedUnionArms::replaceable(function () use (&$ids): never {
            $ids[] = heldDroppedArmGap(droppedArm('thrown'));

            throw new RuntimeException('the read failed');
        }, fn (): bool => true);
    } catch (RuntimeException) {
    }

    DroppedUnionArms::stripped([...$ids, heldDroppedArmGap(droppedArm('outside'))]);

    expect(droppedArmsRunMessages())->toBe([droppedArmPlaceholderMessage('$outside')]);
});

test('a gap warning held by a read that stands still raises when another read holding it is replaced', function () {
    $arm = droppedArm('arm');
    $id = heldDroppedArmGap($arm);

    $again = DroppedUnionArms::replaceable(fn (): int => heldDroppedArmGap($arm), fn (): bool => false);
    DroppedUnionArms::stripped([$id]);

    expect($again)->toBe($id)
        ->and(droppedArmsRunMessages())->toBe([droppedArmPlaceholderMessage('$arm')]);
});

test('a held gap warning that names no placeholder is asked about with no arm, as a raised one is', function () {
    $arm = droppedArm('arm');
    $id = DroppedUnionArms::gap(droppedArmsScope(), 'binding-join', $arm, 'string');
    $asked = [];

    $arms = DroppedUnionArms::replaceable(
        function () use ($id): array {
            DroppedUnionArms::hold($id, droppedArmLeftOutMessage('$arm'), null, stdClass::class);

            return DroppedUnionArms::heldPlaceholderArms();
        },
        function (array $read, ?Expr $arm) use (&$asked): bool {
            $asked[] = $arm;

            return true;
        },
    );
    DroppedUnionArms::stripped([$id]);

    expect($arms)->toBe([])
        ->and($asked)->toBe([null])
        ->and(droppedArmsRunMessages())->toBe([droppedArmLeftOutMessage('$arm')]);
});

test('a provisional read that does not stand discards the gap warnings it held, and one that stands keeps them', function () {
    $ids = [];
    $read = function (string $arm, ?string $value) use (&$ids): ?string {
        $ids[] = heldDroppedArmGap(droppedArm($arm));

        return $value;
    };
    $stands = fn (?string $value): bool => $value !== null;

    DroppedUnionArms::provisional(fn (): ?string => $read('discarded', null), $stands);
    DroppedUnionArms::provisional(fn (): ?string => $read('kept', 'read'), $stands);
    DroppedUnionArms::stripped($ids);

    expect(droppedArmsRunMessages())->toBe([droppedArmPlaceholderMessage('$kept')]);
});

test('the arms the innermost replaceable read holds placeholder warnings for include those held by gap id', function () {
    [$raised, $held] = [droppedArm('raised'), droppedArm('held')];

    $arms = DroppedUnionArms::replaceable(function () use ($raised, $held): array {
        DroppedUnionArms::leftOut(droppedArmsUnion('null'), [[$raised, 'match-arm']], droppedArmsScope());
        heldDroppedArmGap($held);

        return DroppedUnionArms::heldPlaceholderArms();
    }, fn (): bool => false);

    expect($arms)->toBe([$raised, $held]);
});

test('reset() clears the gap ids and their count, the log, the held warnings, the fills and the extents', function () {
    $before = [heldDroppedArmGap(droppedArm('first')), heldDroppedArmGap(droppedArm('second'))];
    DroppedUnionArms::filled([$before[1]]);

    DroppedUnionArms::reset();
    $mark = DroppedUnionArms::gapMark();
    DroppedUnionArms::stripped($before);
    $afterReset = droppedArmsRunMessages();
    $after = DroppedUnionArms::extent(fn (): array => [heldDroppedArmGap(droppedArm('third')), heldDroppedArmGap(droppedArm('fourth'))]);
    $refilled = droppedArmsRunMessages();
    AnalysisWarnings::reset();

    $inside = DroppedUnionArms::extent(function (): array {
        DroppedUnionArms::reset();
        DroppedUnionArms::extent(fn (): int => heldDroppedArmGap(droppedArm('late')));

        return droppedArmsRunMessages();
    });

    expect([...$before, $mark, ...$after])->toBe([1, 2, 0, 1, 2])
        ->and($afterReset)->toBe([])
        ->and($refilled)->toBe([droppedArmPlaceholderMessage('$third'), droppedArmPlaceholderMessage('$fourth')])
        ->and($inside)->toBe([droppedArmPlaceholderMessage('$late')]);
});

test('a gap arm beside the null a drop left still reads as that null', function () {
    $before = DroppedUnionArms::dropped();
    DroppedUnionArms::replay(1);

    expect([
        DroppedUnionArms::leftNullSince('null | $gap4', $before),
        DroppedUnionArms::leftNullSince('$gap4 | null', $before),
        DroppedUnionArms::leftNullSince('null', $before),
        DroppedUnionArms::leftNullSince('string | $gap4', $before),
        DroppedUnionArms::leftNullSince('null | $gap4', DroppedUnionArms::dropped()),
    ])->toBe([true, true, true, false, false]);
});

test('a placeholder beside gap arms warns as the placeholder alone', function (string $type, ?string $published) {
    DroppedUnionArms::leftOut(droppedArmsUnion($type), [[droppedArm('arm'), 'closure-union']], droppedArmsScope());

    expect(droppedArmsRunMessages())->toBe($published === null ? [] : [droppedArmPlaceholderMessage('$arm', $published)]);
})->with([
    'null' => ['null | $gap1', '`null`, as if it were always null'],
    'never[]' => ['never[] | $gap1 | $gap2', '`never[]`, an array that is always empty'],
    'both' => ['$gap3 | null | never[]', '`null | never[]`, as if it were always null or an empty array'],
    'only gaps' => ['$gap1', null],
    'a type' => ['string | $gap1', null],
]);

test('a receiver\'s join leaves a gap per write it warns of in the gap mode, each warning held until a strip (PJ4b-d)', function (bool $shows) {
    [$untyped, $object, $declined] = [droppedArm('untyped'), droppedArm('object'), droppedArm('declined')];
    $scope = droppedArmsScope();
    $read = inGapMode($shows, fn (): array => DroppedUnionArms::leftOutOfRead(droppedArmsUnion('string'), [$untyped], [$object], [], $scope));
    $member = inGapMode($shows, fn (): array => DroppedUnionArms::declined(droppedArmsUnion('number'), [['name' => 'p', 'value' => $declined]], $scope));
    $held = droppedArmsRunMessages();
    DroppedUnionArms::stripped([...VisibleGaps::ids($read['type']), ...VisibleGaps::ids($member['type'])]);

    expect([VisibleGaps::stripped($read['type'])[0], VisibleGaps::stripped($member['type'])[0]])->toBe(['string', 'number'])
        ->and([count(VisibleGaps::ids($read['type'])), count(VisibleGaps::ids($member['type']))])->toBe($shows ? [2, 1] : [0, 0])
        ->and($held)->toHaveCount($shows ? 0 : 3)
        ->and(droppedArmsRunMessages())->toHaveCount(3)
        ->and(droppedArmsRunMessages()[0])->toBe(droppedArmLeftOutMessage('$untyped'));
})->with('gap modes');

test('a gap stripped warns once, as the drop warned with the mode off, and one an extent leaves unstripped as it ends', function () {
    $arm = droppedArm('arm');
    $memo = resolve(AnalysisMemo::class);
    $analysis = new MethodAnalysis;
    $union = $memo->showingGaps(fn (): array => DroppedUnionArms::leftOut(droppedArmsUnion('null'), [[$arm, 'match-arm']], droppedArmsScope()));
    $analysis->addProperty('a', $union);
    $analysis->addProperty('b', $union);
    $analysis->addProperty('c', $union);
    $kept = droppedArmsRunMessages();
    $analysis->stripGaps();
    $stripped = droppedArmsRunMessages();
    AnalysisWarnings::reset();
    DroppedUnionArms::extent(fn (): array => $memo->showingGaps(
        fn (): array => DroppedUnionArms::leftOut(droppedArmsUnion('null'), [[droppedArm('dropped'), 'match-arm']], droppedArmsScope()),
    ));

    expect($kept)->toBe([])
        ->and(array_column($analysis->properties, 'type'))->toBe(['null', 'null', 'null'])
        ->and($stripped)->toBe([droppedArmPlaceholderMessage('$arm')])
        ->and(droppedArmsRunMessages())->toBe([droppedArmPlaceholderMessage('$dropped')]);
});
