<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Arrays\Build;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Ops\JoinOptions;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Ops\ShapeJoin;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Ops\ShapeWrites;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Print\ShapeLowering;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Read\ArrayShapes;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ArrayShape;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\KeyPresence;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\OpaquePart;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeKey;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeObserver;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeSignature;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\IndexSignatureKey;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
use ArrayObject;
use Closure;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Variable;

/**
 * The keys a returned variable holds as its walk lands each write, after PHPStan's constant array builder, held as an
 * ArrayShape that ShapeWrites writes as MethodAnalysis would. The statement walker decides what each statement writes
 * and which bodies join; the host's reads arrive as closures.
 *
 * @template TAnalysis of MethodAnalysis
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type HeldNulls from ExpressionHandler
 * @phpstan-import-type Entry from ShapeWrites
 *
 * @phpstan-type UntypedKeyWrite = array{string, Expr}
 *
 * @internal
 */
final class ArrayWalk
{
    /** @var array<string, WalkKeyState> what some path leaves each key holding, as `??=` reads it */
    private array $paths = [];

    /** @var list<UntypedKeyWrite> each key the walk wrote a value the engine cannot type, with that value, in order */
    private array $untyped = [];

    /** @var list<OpaquePart> each part that brings keys the walk holds unread, in the order the walk met them */
    private array $opaque = [];

    /** The keys the walk holds and what it holds beside them; its opaque parts are kept apart, in $opaque. */
    private ArrayShape $held;

    /**
     * Hold a walk's reads beside the host's. Every branch of the walk shares $vanishing: a mark any path makes stays
     * for the rest of the walk.
     *
     * @param  ArrayObject<string, true>  $vanishing  each key given a value that may be removed, as `when()`'s is
     * @param  Closure(): TAnalysis  $fresh  a new, empty analysis of the host's type
     * @param  Closure(ValueExpressionResult): ValueExpressionResult  $arrayValue  a value as an array sends it
     * @param  Closure(Expr): ?string  $namedKey  the key a literal's item names, as the host names one
     */
    private function __construct(
        private readonly ArrayObject $vanishing,
        private readonly string $varName,
        private readonly Closure $fresh,
        private readonly Closure $arrayValue,
        private readonly Closure $namedKey,
    ) {
        $this->held = new ArrayShape;
    }

    /**
     * A walk of a variable, holding the keys $seed gives a parameter on entry or none.
     *
     * @template T of MethodAnalysis
     *
     * @param  Closure(): T  $fresh
     * @param  Closure(ValueExpressionResult): ValueExpressionResult  $arrayValue
     * @param  Closure(Expr): ?string  $namedKey
     * @return self<T>
     */
    public static function from(
        ?ArrayShape $seed,
        string $varName,
        Closure $fresh,
        Closure $arrayValue,
        Closure $namedKey,
    ): self {
        $walk = new self(new ArrayObject, $varName, $fresh, $arrayValue, $namedKey);

        if ($seed !== null) {
            $walk->held = ShapeWrites::seeded($seed);
            $walk->noteVanishing($walk->held);
        }

        return $walk;
    }

    /**
     * Each item of an array literal whose key its analysis types `unknown`, with its value; none for another value.
     *
     * @param  Closure(Expr): ?string  $namedKey
     * @return list<UntypedKeyWrite>
     */
    public static function untypedItems(Expr $array, MethodAnalysis $analysis, Closure $namedKey): array
    {
        return self::untypedItemsOf($array, array_column($analysis->properties, 'type', 'name'), $namedKey);
    }

    /**
     * Whether an array literal spreads the named variable into itself, as `[...$data, 'k' => $v]` does.
     */
    public static function spreadsVariable(Expr $value, string $varName): bool
    {
        return $value instanceof Array_ && array_any(
            $value->items,
            static fn (ArrayItem $item): bool => $item->unpack
                && $item->value instanceof Variable && $item->value->name === $varName,
        );
    }

    /**
     * The keys the walk holds, lowered into a new analysis of the host's type: what a write that reads the variable
     * reads, and the walk's result.
     *
     * @return TAnalysis
     */
    public function state(): MethodAnalysis
    {
        return $this->lowered($this->shape());
    }

    /**
     * The keys the walk holds, as a shape naming the site of each part it holds unread.
     */
    public function shape(): ArrayShape
    {
        $held = $this->held;

        return new ArrayShape($held->entries, $held->extras, [...$held->opaque, ...$this->opaque], false, $held->wide);
    }

    /**
     * Each key the walk wrote a value the engine cannot type, with that value, in write order.
     *
     * @return list<UntypedKeyWrite>
     */
    public function untypedWrites(): array
    {
        return $this->untyped;
    }

    /**
     * Whether the walk holds a key.
     */
    public function holds(string $key): bool
    {
        return ShapeWrites::firstOf($this->held->entries, $key) !== null;
    }

    /**
     * Land a whole write `$var = value` of a value read as $whole. A write that always runs replaces every key held
     * before it, and every part held unread, unless its value spreads the variable into itself.
     */
    public function assign(ArrayShape $whole, bool $conditional, Expr $value): void
    {
        if (! $conditional && ! self::spreadsVariable($value, $this->varName)) {
            $this->held = new ArrayShape;
            $this->opaque = [];
        }

        $this->noteUntypedItems($value, $whole);
        $this->merge($whole, $conditional, $value);
    }

    /**
     * Land a `$var += value` of a value read as $added: PHP's union keeps every key already set, so only the new keys
     * and each signature entry join, and a key the walk keeps drops the value's channels along with its value.
     */
    public function plus(ArrayShape $added, bool $conditional, Expr $value): void
    {
        $write = ShapeWrites::withoutKeys($added, array_map(static fn (ShapeKey|ShapeSignature $entry): string => $entry->writtenName(), $this->held->entries));
        $this->noteUntypedItems($value, $write);
        $this->merge($write, $conditional, $value);
    }

    /**
     * Land a key write. A signature key is one more runtime key its signature covers; a named key lands as
     * writeName() lands it. $written, where given, is the value the statement writes, noted where the engine cannot
     * type it.
     *
     * @param  ValueExpressionResult  $result
     */
    public function write(string $key, array $result, bool $conditional, ?Expr $written = null): void
    {
        if (! IndexSignatureKey::is($key)) {
            $this->writeName($key, $result, $conditional, $written);

            return;
        }

        $this->noteUntyped($key, $result, $written);
        $this->held = ShapeWrites::signatureWritten($this->held, $key, ($this->arrayValue)($result));
    }

    /**
     * Land a write of the key PHP stores under this very name, as an appended attribute name is, whatever text it
     * spells: written again, it keeps its first position and takes the last value.
     *
     * @param  ValueExpressionResult  $result
     */
    public function writeName(string $key, array $result, bool $conditional, ?Expr $written = null): void
    {
        $this->noteUntyped($key, $result, $written);
        $this->held = $this->written($this->held, $key, $result, $conditional);
    }

    /**
     * Fill a held key as `??=` does: a value that is not null stays, and the default lands where it is unset or null.
     *
     * A value left out itself, as `when()` is, or a closure or Inertia prop object, is set and not null, so it stays as
     * published, and the default lands only past a plain null or a path that leaves the key unset.
     *
     * @param  Closure(MethodAnalysis): ValueExpressionResult  $default  the default, read against the keys held
     */
    public function coalesce(string $key, Closure $default, bool $conditional): void
    {
        $at = ShapeWrites::firstOf($this->held->entries, $key);

        if ($at === null) {
            return;
        }

        $heldType = $this->held->entries[$at]->value->type();
        $heldOptional = ShapeWrites::presenceOf($this->held->entries[$at])->optional;
        $signature = IndexSignatureKey::is($key);
        $paths = self::pathsIn($this->paths, $key, $heldType);
        $leftOut = isset($this->vanishing[$key]);
        // PHP holds an object, never null, where Inertia later resolves it, so only a plain value's null runs `??=`.
        $nullLands = $paths->object ? $paths->plainNull : ValueResult::mayBeNull($heldType);
        $keptType = $paths->objectNull ? $heldType : TsTypeString::withoutNullArm($heldType);

        if (! ($signature || $nullLands || ($heldOptional && ($paths->unset || (! $leftOut && ! $paths->object))))) {
            return;
        }

        $filling = $default($this->state());

        // A signature stands for many runtime keys, so the default is one more it may cover.
        if ($signature) {
            $this->held = ShapeWrites::signatureWritten($this->held, $key, ($this->arrayValue)($filling));

            return;
        }

        $kept = ShapeWrites::mapped(
            $this->held,
            $key,
            static fn (ShapeKey|ShapeSignature $entry): ShapeKey|ShapeSignature => ShapeWrites::withValue(
                $entry,
                ShapeWrites::retyped($entry->value, $keptType),
                ShapeWrites::presenceOf($entry)->held(),
            ),
        );
        $filled = $this->written($this->held, $key, $filling, false);
        [$heldBare, $heldGaps] = VisibleGaps::aside($heldType);
        $onlyNull = ! $paths->objectNull && TsTypeString::splitTopLevelUnion($heldBare) === ['null'];
        $joined = $this->joined($conditional ? [$this->held, $filled] : ($onlyNull ? [$filled] : [$kept, $filled]));
        $untyped = (! $onlyNull && $keptType === 'unknown') || $filling['type'] === 'unknown';

        if ($untyped) {
            $joined = ShapeWrites::withoutChannels($joined, $key);
        }

        // A value left out itself may still be on each path the default does not land, as a `when()` holding null is.
        // A gap held beside the null is a value `??=` keeps, which the default's lone branch would drop.
        $this->held = ShapeWrites::mapped($joined, $key, static function (ShapeKey|ShapeSignature $entry) use (
            $untyped, $onlyNull, $heldGaps, $leftOut, $heldOptional,
        ): ShapeKey|ShapeSignature {
            $type = $entry->value->type();
            $presence = ShapeWrites::presenceOf($entry);

            return ShapeWrites::withValue($entry, ShapeWrites::retyped(
                $entry->value,
                $untyped ? 'unknown' : ($onlyNull ? VisibleGaps::restored($type, $heldGaps) : $type),
            ), new KeyPresence(
                $presence->optional || ($leftOut && $heldOptional),
                $presence->absent,
                $presence->removed || ($leftOut && $heldOptional),
            ));
        });
        $fills = WalkKeyState::ofValue(ValueResult::heldNullsOf($filling), $filling['type']);
        $this->paths[$key] = new WalkKeyState(
            $conditional && $paths->unset,
            $paths->object || $fills->object,
            $paths->objectNull || $fills->objectNull,
            $fills->plainNull || ($conditional && $paths->plainNull),
        );
    }

    /**
     * Hold the keys a part brings that no name the walk reads names, so the walk is not read completely.
     */
    public function holdOpaque(OpaquePart $part): void
    {
        $this->opaque[] = $part;
        $this->held = ShapeWrites::withFlags($this->held, readsCompletely: false, holdsUnreadKeys: true);
    }

    /**
     * Take a key an unset() names from the walk, with its channels and cast, or make it optional where the unset() may
     * not run. One that names no key may take any, so the walk is no longer read completely, though it brings none.
     */
    public function unset(?string $key, bool $conditional): void
    {
        if ($key === null) {
            $this->held = ShapeWrites::withFlags($this->held, readsCompletely: false);

            return;
        }

        if (! $conditional) {
            $this->held = ShapeWrites::without($this->held, $key);

            return;
        }

        $type = ShapeWrites::typesOf($this->held)[$key] ?? null;

        if ($type !== null) {
            $this->markUnset($key, $type);
        }

        $this->held = ShapeWrites::mapped($this->held, $key, self::mayLose(...));
    }

    /**
     * Make each key the walk holds optional before a whole write it cannot read, which may replace them all, and hold
     * the keys $brought brings unread; an unset() brings none.
     */
    public function loosen(?OpaquePart $brought): void
    {
        $this->held = ShapeWrites::mapped($this->held, null, self::mayLose(...));

        foreach ($this->held->entries as $entry) {
            $this->markUnset($entry->writtenName(), $entry->value->type());
        }

        if ($brought !== null) {
            $this->holdOpaque($brought);
        }
    }

    /**
     * Join the walks of exclusive bodies, each a copy of this walk that wrote one body, where one runs, or none where
     * $mayRunNone: this walk's keys are one more branch then, and lead each union. A key a body writes takes that
     * body's value there, never another's, and each body's untyped writes stay its own. $recordDrops records each
     * value the join leaves out, beside the untyped writes each branch saw, and may retype a key it names.
     *
     * @param  non-empty-list<self<TAnalysis>>  $bodies
     * @param  Closure(TAnalysis, non-empty-list<TAnalysis>, list<list<UntypedKeyWrite>>): void  $recordDrops
     */
    public function branches(array $bodies, bool $mayRunNone, Closure $recordDrops): void
    {
        $walks = $mayRunNone ? [$this, ...$bodies] : $bodies;
        $shapes = array_map(static fn (self $walk): ArrayShape => $walk->held, $walks);
        $added = [];
        $opaque = [];

        foreach ($bodies as $body) {
            array_push($added, ...array_slice($body->untyped, count($this->untyped)));
        }

        // Each body copies the parts held before it, so a part every branch holds is one part.
        foreach ($walks as $walk) {
            foreach ($walk->opaque as $part) {
                if (! in_array($part, $opaque, true)) {
                    $opaque[] = $part;
                }
            }
        }

        $paths = $this->pathsPast($shapes, array_map(static fn (self $walk): array => $walk->paths, $walks));
        $notes = array_map(static fn (self $walk): array => $walk->untyped, $walks);
        $branches = array_map(static fn (self $walk): MethodAnalysis => $walk->state(), $walks);
        $this->held = $this->joined(
            $shapes,
            static fn (MethodAnalysis $joined) => $recordDrops($joined, $branches, $notes),
        );
        $this->untyped = [...$this->untyped, ...$added];
        $this->paths = $paths;
        $this->opaque = $opaque;
    }

    /**
     * Each item of an array literal whose key the write types `unknown`, by the type each key holds.
     *
     * @param  array<array-key, string>  $types  each key's type, by name
     * @param  Closure(Expr): ?string  $namedKey
     * @return list<UntypedKeyWrite>
     */
    private static function untypedItemsOf(Expr $array, array $types, Closure $namedKey): array
    {
        if (! $array instanceof Array_) {
            return [];
        }

        $untyped = [];

        foreach ($array->items as $item) {
            $key = $item->key === null || $item->unpack ? null : $namedKey($item->key);

            if ($key !== null && ($types[$key] ?? null) === 'unknown') {
                $untyped[] = [$key, $item->value];
            }
        }

        return $untyped;
    }

    /**
     * What some path leaves a key holding in a record, or, for a key no write recorded, what its own type says.
     *
     * @param  array<string, WalkKeyState>  $record
     */
    private static function pathsIn(array $record, string $key, string $type): WalkKeyState
    {
        return $record[$key] ?? WalkKeyState::ofType($type);
    }

    /**
     * A held key where some path may leave it unset: the join of the path that keeps it and one that lacks it.
     *
     * @param  Entry  $entry
     * @return Entry
     */
    private static function mayLose(ShapeKey|ShapeSignature $entry): ShapeKey|ShapeSignature
    {
        $lost = KeyPresence::join([ShapeWrites::presenceOf($entry)], inEveryBranch: false);

        return ShapeWrites::withValue($entry, $entry->value, $lost);
    }

    /**
     * A shape lowered into a new analysis of the host's type.
     *
     * @return TAnalysis
     */
    private function lowered(ArrayShape $shape): MethodAnalysis
    {
        $analysis = ($this->fresh)();
        ShapeLowering::into($analysis, $shape);

        return $analysis;
    }

    /**
     * Exclusive branches joined as a ternary's arms join: an untypable value drops out beside a typed one, and a lone
     * `null` stays. The join is lowered for the observer and $recorded, which may retype a key, then read back.
     *
     * @param  non-empty-list<ArrayShape>  $branches
     * @param  (Closure(TAnalysis): void)|null  $recorded
     */
    private function joined(array $branches, ?Closure $recorded = null): ArrayShape
    {
        $joined = $this->lowered(ShapeJoin::exclusive(
            $branches,
            new JoinOptions(dropsUntypedBranches: true, keepsLoneNull: true),
        ));
        resolve(ShapeObserver::class)->observed($joined);

        if ($recorded !== null) {
            $recorded($joined);
        }

        return ArrayShapes::ofAnalysis($joined);
    }

    /**
     * What each key holds past a join of branches: no value where a branch leaves it out, and what any branch holds.
     *
     * @param  list<ArrayShape>  $branches
     * @param  list<array<string, WalkKeyState>>  $records  each branch's record, in branch order
     * @return array<string, WalkKeyState>
     */
    private function pathsPast(array $branches, array $records): array
    {
        $types = array_map(ShapeWrites::typesOf(...), $branches);
        $names = [];

        foreach ($types as $held) {
            $names += $held;
        }

        $joined = [];

        foreach (array_keys($names) as $name) {
            $name = (string) $name;
            $paths = new WalkKeyState;

            foreach ($types as $index => $held) {
                $paths = $paths->either(isset($held[$name])
                    ? self::pathsIn($records[$index], $name, $held[$name])
                    : WalkKeyState::neverSet());
            }

            $joined[$name] = $paths;
        }

        return $joined;
    }

    /**
     * Note that a path may leave a key the walk holds with no value, beside what other paths leave it holding.
     */
    private function markUnset(string $key, string $type): void
    {
        $this->paths[$key] = self::pathsIn($this->paths, $key, $type)->withUnset();
    }

    /**
     * Record what a write of a value leaves a key holding, beside what it held before where the write may not run.
     *
     * @param  string|null  $heldType  the type the key held before the write, null where it held none
     * @param  HeldNulls|null  $held  ValueResult::heldNullsOf() of the value written
     */
    private function recordWrite(
        string $key,
        bool $conditional,
        ?string $heldType,
        bool $unset,
        ?array $held,
        string $type,
    ): void {
        $paths = WalkKeyState::ofValue($held, $type, $unset);

        if ($conditional) {
            $before = $heldType === null ? WalkKeyState::neverSet() : self::pathsIn($this->paths, $key, $heldType);
            $paths = $paths->either($before);
        }

        $this->paths[$key] = $paths;
    }

    /**
     * Note each key a write sets to a value that may be removed, since the walk's own writes go through written().
     */
    private function noteVanishing(ArrayShape $write): void
    {
        foreach ($write->entries as $entry) {
            // A removed value is set and not null, so `??=` leaves it; a key a merge may leave unset takes the default.
            if (ShapeWrites::presenceOf($entry)->removed) {
                $this->vanishing[$entry->writtenName()] = true;
            }
        }
    }

    /**
     * Note a key write whose value the engine cannot type, which a join may leave out beside a typed branch.
     *
     * @param  ValueExpressionResult  $result
     */
    private function noteUntyped(string $key, array $result, ?Expr $written): void
    {
        if ($written !== null && $result['type'] === 'unknown') {
            $this->untyped[] = [$key, $written];
        }
    }

    /**
     * Note each item of an array literal written whole whose key the write types `unknown`.
     */
    private function noteUntypedItems(Expr $array, ArrayShape $write): void
    {
        array_push($this->untyped, ...self::untypedItemsOf($array, ShapeWrites::typesOf($write), $this->namedKey));
    }

    /**
     * Merge a whole-array write into the keys the walk holds, noting what each written key holds on each path.
     *
     * A new key or a signature entry is appended, optional when the write is conditional or its value can vanish; a
     * named key set again keeps its first position and takes the last value, optional when that value can vanish or a
     * conditional write leaves an optional old one.
     */
    private function merge(ArrayShape $write, bool $conditional, Expr $value): void
    {
        $this->noteVanishing($write);
        $unset = $this->keysLeftUnset($value, $write);

        foreach ($write->entries as $entry) {
            $name = $entry->writtenName();
            // A signature entry is one more runtime key its signature covers, so it replaces no earlier entry.
            $at = IndexSignatureKey::is($name) ? null : ShapeWrites::firstOf($this->held->entries, $name);
            $this->recordWrite(
                $name,
                $conditional,
                $at === null ? null : $this->held->entries[$at]->value->type(),
                isset($unset[$name]),
                $entry->value->channels['heldObjectKeys'] ?? null,
                $entry->value->type(),
            );
        }

        $this->held = ShapeWrites::merged($this->held, $write, $conditional);
    }

    /**
     * The keys a whole write's value may leave with no PHP value.
     *
     * In an array literal, those only a keyless item brings, as `mergeWhen()` does once Laravel filters the array.
     * Another value says nothing of which keys it sets, so it leaves none unset.
     *
     * @return array<string, true>
     */
    private function keysLeftUnset(Expr $value, ArrayShape $write): array
    {
        if (! $value instanceof Array_
            || ! array_any($value->items, static fn (ArrayItem $item): bool => $item->key === null && ! $item->unpack)) {
            return [];
        }

        $named = array_map(
            fn (ArrayItem $item): ?string => $item->key === null ? null : ($this->namedKey)($item->key),
            $value->items,
        );
        $unset = [];

        foreach ($write->entries as $entry) {
            $name = $entry->writtenName();

            if (! in_array($name, $named, true)) {
                $unset[$name] = true;
            }
        }

        return $unset;
    }

    /**
     * A shape with one named key written: a re-assigned key is the last write winning, in the first write's position,
     * missing where its new value vanishes, or where the write is skipped and no key, or a missing one, was held.
     *
     * @param  ValueExpressionResult  $result
     */
    private function written(ArrayShape $shape, string $key, array $result, bool $conditional): ArrayShape
    {
        $at = ShapeWrites::firstOf($shape->entries, $key);
        $held = $at === null ? null : $shape->entries[$at];

        if ($result['optional']) {
            $this->vanishing[$key] = true;
        }

        $this->recordWrite($key, $conditional, $held?->value->type(), false, ValueResult::heldNullsOf($result), $result['type']);
        $value = ($this->arrayValue)($result);
        $presence = KeyPresence::written(
            $result['optional'],
            $value['optional'],
            $held === null ? null : ShapeWrites::presenceOf($held),
            $conditional,
        );

        return ShapeWrites::keyWritten($shape, $key, $value, $conditional || $result['optional'], $presence);
    }
}
