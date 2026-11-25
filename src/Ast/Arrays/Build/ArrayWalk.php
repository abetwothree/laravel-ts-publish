<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Arrays\Build;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Print\ShapeLowering;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Read\ArrayShapes;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ArrayShape;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\KeyPresence;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\OpaquePart;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\IndexSignatureKey;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
use ArrayObject;
use Closure;
use LogicException;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Variable;

/**
 * The keys a returned variable holds as its walk lands each write, after PHPStan's constant array builder, still as
 * the rows of a MethodAnalysis. The statement walker decides what each statement writes and which bodies join; the
 * host's reads arrive as closures.
 *
 * @template TAnalysis of MethodAnalysis
 *
 * @phpstan-import-type AnalyzedProperty from MethodAnalysis
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type HeldNulls from ExpressionHandler
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

    /**
     * Hold the keys a walk holds beside the host's reads. Every branch of the walk shares $vanishing: a mark any path
     * makes stays for the rest of the walk.
     *
     * @param  TAnalysis  $into
     * @param  ArrayObject<string, true>  $vanishing  each key given a value that may be removed, as `when()`'s is
     * @param  Closure(): TAnalysis  $fresh  a new, empty analysis of the host's type
     * @param  Closure(non-empty-list<TAnalysis>): TAnalysis  $join  the host's join of exclusive branches
     * @param  Closure(ValueExpressionResult): ValueExpressionResult  $arrayValue  a value as an array sends it
     * @param  Closure(Expr): ?string  $namedKey  the key a literal's item names, as the host names one
     */
    private function __construct(
        private MethodAnalysis $into,
        private readonly ArrayObject $vanishing,
        private readonly string $varName,
        private readonly Closure $fresh,
        private readonly Closure $join,
        private readonly Closure $arrayValue,
        private readonly Closure $namedKey,
    ) {}

    /**
     * Copy the keys for a branch to write into; its paths, untyped writes and opaque parts copy with it.
     */
    public function __clone()
    {
        $this->into = clone $this->into;
    }

    /**
     * A walk of a variable, holding the keys $seed gives a parameter on entry or none.
     *
     * @template T of MethodAnalysis
     *
     * @param  Closure(): T  $fresh
     * @param  Closure(non-empty-list<T>): T  $join
     * @param  Closure(ValueExpressionResult): ValueExpressionResult  $arrayValue
     * @param  Closure(Expr): ?string  $namedKey
     * @return self<T>
     */
    public static function from(
        ?ArrayShape $seed,
        string $varName,
        Closure $fresh,
        Closure $join,
        Closure $arrayValue,
        Closure $namedKey,
    ): self {
        $walk = new self($fresh(), new ArrayObject, $varName, $fresh, $join, $arrayValue, $namedKey);

        if ($seed !== null) {
            $held = self::lowered($seed);
            $walk->into->merge($held);
            $walk->noteVanishing($held);
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
        if (! $array instanceof Array_) {
            return [];
        }

        $types = array_column($analysis->properties, 'type', 'name');
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
     * The keys the walk holds, as rows: what a write that reads the variable reads, and the walk's result.
     *
     * @return TAnalysis
     */
    public function state(): MethodAnalysis
    {
        return $this->into;
    }

    /**
     * The keys the walk holds, as a shape naming the site of each part it holds unread.
     */
    public function shape(): ArrayShape
    {
        $held = ArrayShapes::ofAnalysis($this->into);

        return new ArrayShape($held->entries, $held->extras, $this->opaque, $held->nonEmptyProof, $held->wide);
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
        return in_array($key, array_column($this->into->properties, 'name'), true);
    }

    /**
     * Land a whole write `$var = value` of a value read as $whole. A write that always runs replaces every key held
     * before it, and every part held unread, unless its value spreads the variable into itself.
     */
    public function assign(ArrayShape $whole, bool $conditional, Expr $value): void
    {
        $write = self::lowered($whole);

        if (! $conditional && ! self::spreadsVariable($value, $this->varName)) {
            $this->into->replaceWith(($this->fresh)());
            $this->opaque = [];
        }

        $this->noteUntypedItems($value, $write);
        $this->merge($write, $conditional, $value);
    }

    /**
     * Land a `$var += value` of a value read as $added: PHP's union keeps every key already set, so only the new keys
     * and each signature entry join, and a key the walk keeps drops the value's channels along with its value.
     */
    public function plus(ArrayShape $added, bool $conditional, Expr $value): void
    {
        $write = self::lowered($added);
        $write->dropKeysHeldBy($this->into);
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
        $this->into->addSignatureEntry($key, ($this->arrayValue)($result));
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
        $this->writeInto($this->into, $key, $result, $conditional);
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
        $held = array_find($this->into->properties, static fn (array $property): bool => $property['name'] === $key);

        if ($held === null) {
            return;
        }

        $signature = IndexSignatureKey::is($key);
        $paths = self::pathsIn($this->paths, $key, $held['type']);
        $leftOut = isset($this->vanishing[$key]);
        // PHP holds an object, never null, where Inertia later resolves it, so only a plain value's null runs `??=`.
        $nullLands = $paths->object ? $paths->plainNull : ValueResult::mayBeNull($held['type']);
        $keptType = $paths->objectNull ? $held['type'] : TsTypeString::withoutNullArm($held['type']);

        if (! ($signature || $nullLands || ($held['optional'] && ($paths->unset || (! $leftOut && ! $paths->object))))) {
            return;
        }

        $filling = $default($this->into);

        // A signature stands for many runtime keys, so the default is one more it may cover.
        if ($signature) {
            $this->into->addSignatureEntry($key, ($this->arrayValue)($filling));

            return;
        }

        $kept = clone $this->into;
        $kept->properties = array_map(
            static fn (array $property): array => $property['name'] === $key
                ? KeyPresence::ofRow($property)->held()->onRow([...$property, 'type' => $keptType])
                : $property,
            $this->into->properties,
        );
        $filled = clone $this->into;
        $this->writeInto($filled, $key, $filling, false);
        [$heldBare, $heldGaps] = VisibleGaps::aside($held['type']);
        $onlyNull = ! $paths->objectNull && TsTypeString::splitTopLevelUnion($heldBare) === ['null'];
        $branches = $conditional ? [clone $this->into, $filled] : ($onlyNull ? [$filled] : [$kept, $filled]);

        $joined = ($this->join)($branches);
        $untyped = (! $onlyNull && $keptType === 'unknown') || $filling['type'] === 'unknown';

        if ($untyped) {
            $joined->forgetChannels($key);
        }

        // A value left out itself may still be on each path the default does not land, as a `when()` holding null is.
        // A gap held beside the null is a value `??=` keeps, which the default's lone branch would drop.
        $joined->properties = array_map(
            static fn (array $property): array => $property['name'] === $key ? MethodAnalysis::withPresence(
                [
                    ...$property,
                    'type' => $untyped
                        ? 'unknown'
                        : ($onlyNull ? VisibleGaps::restored($property['type'], $heldGaps) : $property['type']),
                ],
                optional: $property['optional'] || ($leftOut && $held['optional']),
                absent: $property['absent'] ?? false,
                removed: ($property['removed'] ?? false) || ($leftOut && $held['optional']),
            ) : $property,
            $joined->properties,
        );

        $this->into->replaceWith($joined);
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
        $this->into->holdUnreadKeys();
    }

    /**
     * Take a key an unset() names from the walk, with its channels and cast, or make it optional where the unset() may
     * not run. One that names no key may take any, so the walk is no longer read completely, though it brings none.
     */
    public function unset(?string $key, bool $conditional): void
    {
        if ($key === null) {
            $this->into->readsCompletely = false;

            return;
        }

        if ($conditional) {
            $type = array_column($this->into->properties, 'type', 'name')[$key] ?? null;

            if ($type !== null) {
                $this->markUnset($key, $type);
            }

            $this->into->properties = array_map(
                static fn (array $property): array => $property['name'] === $key ? self::mayLose($property) : $property,
                $this->into->properties,
            );

            return;
        }

        $this->into->forgetChannels($key);
        $this->into->properties = array_values(array_filter(
            $this->into->properties,
            static fn (array $property): bool => $property['name'] !== $key,
        ));
    }

    /**
     * Make each key the walk holds optional before a whole write it cannot read, which may replace them all, and hold
     * the keys $brought brings unread; an unset() brings none.
     */
    public function loosen(?OpaquePart $brought): void
    {
        $this->into->properties = array_map(self::mayLose(...), $this->into->properties);

        foreach ($this->into->properties as $property) {
            $this->markUnset($property['name'], $property['type']);
        }

        if ($brought !== null) {
            $this->holdOpaque($brought);
        }
    }

    /**
     * Join the walks of exclusive bodies, each a copy of this walk that wrote one body, where one runs, or none where
     * $mayRunNone: this walk's keys are one more branch then, and lead each union. A key a body writes takes that
     * body's value there, never another's, and each body's untyped writes stay its own. $recordDrops records each
     * value the join leaves out, beside the untyped writes each branch saw.
     *
     * @param  non-empty-list<self<TAnalysis>>  $bodies
     * @param  Closure(TAnalysis, non-empty-list<TAnalysis>, list<list<UntypedKeyWrite>>): void  $recordDrops
     */
    public function branches(array $bodies, bool $mayRunNone, Closure $recordDrops): void
    {
        $walks = $mayRunNone ? [$this, ...$bodies] : $bodies;
        $branches = array_map(static fn (self $walk): MethodAnalysis => $walk->into, $walks);
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

        $paths = $this->pathsPast($branches, array_map(static fn (self $walk): array => $walk->paths, $walks));
        $notes = array_map(static fn (self $walk): array => $walk->untyped, $walks);
        $joined = ($this->join)($branches);
        $recordDrops($joined, $branches, $notes);
        $this->untyped = [...$this->untyped, ...$added];
        $this->paths = $paths;
        $this->opaque = $opaque;
        $this->into->replaceWith($joined);
    }

    /**
     * A shape lowered into a fresh analysis.
     */
    private static function lowered(ArrayShape $shape): MethodAnalysis
    {
        $analysis = new MethodAnalysis;
        ShapeLowering::into($analysis, $shape);

        return $analysis;
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
     * A held key's row where some path may leave it unset: the join of the path that keeps it and one that lacks it.
     *
     * @param  AnalyzedProperty  $property
     * @return AnalyzedProperty
     */
    private static function mayLose(array $property): array
    {
        return KeyPresence::join([KeyPresence::ofRow($property)], inEveryBranch: false)->onRow($property);
    }

    /**
     * What each key holds past a join of branches: no value where a branch leaves it out, and what any branch holds.
     *
     * @param  list<MethodAnalysis>  $branches
     * @param  list<array<string, WalkKeyState>>  $records  each branch's record, in branch order
     * @return array<string, WalkKeyState>
     */
    private function pathsPast(array $branches, array $records): array
    {
        $types = array_map(
            static fn (MethodAnalysis $branch): array => array_column($branch->properties, 'type', 'name'),
            $branches,
        );
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
     * Note each key a write sets to a value that may be removed, since the walk's own writes go through writeInto().
     */
    private function noteVanishing(MethodAnalysis $write): void
    {
        foreach ($write->properties as $property) {
            // A removed value is set and not null, so `??=` leaves it; a key a merge may leave unset takes the default.
            if ($property['removed'] ?? false) {
                $this->vanishing[$property['name']] = true;
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
     * Note each item of an array literal written whole whose key the write's analysis types `unknown`.
     */
    private function noteUntypedItems(Expr $array, MethodAnalysis $write): void
    {
        array_push($this->untyped, ...self::untypedItems($array, $write, $this->namedKey));
    }

    /**
     * Merge a whole-array write into the keys the walk holds.
     *
     * A new key or a signature entry is appended, optional when the write is conditional or its value can vanish; a
     * named key set again keeps its first position and takes the last value, optional when that value can vanish or a
     * conditional write leaves an optional old one.
     */
    private function merge(MethodAnalysis $write, bool $conditional, Expr $value): void
    {
        $this->noteVanishing($write);
        $unset = $this->keysLeftUnset($value, $write);
        $properties = $this->into->properties;
        $names = array_column($properties, 'name');

        // A literal's fold writes each named key once, so the rows below never meet a key the write repeats.
        if (count($write->writtenProperties()) !== count($write->properties)) {
            throw new LogicException('A whole write holds each named key once; a signature entry is one more runtime key.');
        }

        foreach ($write->properties as $prop) {
            // A signature entry is one more runtime key its signature covers, so it replaces no earlier entry.
            $index = IndexSignatureKey::is($prop['name']) ? false : array_search($prop['name'], $names, true);
            $heldType = $index === false ? null : $properties[$index]['type'];
            $this->recordWrite(
                $prop['name'],
                $conditional,
                $heldType,
                isset($unset[$prop['name']]),
                $write->heldObjectKeys[$prop['name']] ?? null,
                $prop['type'],
            );
            $held = $index === false ? KeyPresence::missing() : KeyPresence::ofRow($properties[$index]);
            $laid = KeyPresence::ofRow($prop)->after($held, $conditional)->onRow($prop);

            if ($index === false) {
                $properties[] = $laid;

                continue;
            }

            $this->into->forgetChannels($prop['name']);
            $properties[$index] = $laid;
        }

        // merge() takes keys the walk does not hold, so only the channels move; the rows are the ones laid above.
        $this->into->properties = [];
        $this->into->merge($write);
        $this->into->properties = $properties;
    }

    /**
     * The keys a whole write's value may leave with no PHP value.
     *
     * In an array literal, those only a keyless item brings, as `mergeWhen()` does once Laravel filters the array.
     * Another value says nothing of which keys it sets, so it leaves none unset.
     *
     * @return array<string, true>
     */
    private function keysLeftUnset(Expr $value, MethodAnalysis $write): array
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

        foreach ($write->properties as $property) {
            if (! in_array($property['name'], $named, true)) {
                $unset[$property['name']] = true;
            }
        }

        return $unset;
    }

    /**
     * Write one named key into the keys an analysis holds. A re-assigned key is the last write winning, in the first
     * write's position: every stale channel goes before addProperty() routes this result's own, and the entry it
     * appends is folded back over the earlier one.
     *
     * @param  ValueExpressionResult  $result
     */
    private function writeInto(MethodAnalysis $into, string $keyName, array $result, bool $conditional): void
    {
        $existingIndex = null;

        foreach ($into->properties as $index => $existing) {
            if ($existing['name'] === $keyName) {
                $existingIndex = $index;

                break;
            }
        }

        $into->forgetChannels($keyName);

        if ($result['optional']) {
            $this->vanishing[$keyName] = true;
        }

        $held = $existingIndex === null ? null : $into->properties[$existingIndex];
        $heldType = $held['type'] ?? null;
        $this->recordWrite($keyName, $conditional, $heldType, false, ValueResult::heldNullsOf($result), $result['type']);
        $appendedIndex = count($into->properties);
        $value = ($this->arrayValue)($result);

        $into->addProperty($keyName, $value, $conditional || $result['optional']);

        // A key is missing where its new value vanishes, or where this write is skipped and no key, or a missing one,
        // was held.
        if (isset($into->properties[$appendedIndex])) {
            $presence = KeyPresence::written(
                $result['optional'],
                $value['optional'],
                $held === null ? null : KeyPresence::ofRow($held),
                $conditional,
            );
            $into->properties[$existingIndex ?? $appendedIndex] = $presence->onRow($into->properties[$appendedIndex]);
        }

        if ($existingIndex !== null) {
            array_splice($into->properties, $appendedIndex, 1);
        }
    }
}
