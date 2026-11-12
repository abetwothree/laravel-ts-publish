<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Analysis\Fold;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\BranchJoin;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Values\InlineObjectResult;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ObjectMembers;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
use LogicException;
use PhpParser\Node;

/**
 * The key writes of one array literal, held apart from the analysis they become: the item loop records each item's
 * keys here in item order, and applyTo() folds them (LiteralFold) and writes each key once, with the classes and
 * casts of every value it can send, beside the spreads a nested literal's print keeps as arms.
 *
 * A source is what one write's value comes from: a keyed item's value, a spread member's, or the analysis a helper,
 * a parent, a filter, an arm or a merge call reads as. A signature entry is one more runtime key, never folded, and
 * keeps its place among the keys.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type KeyedValues from ObjectMembers
 * @phpstan-import-type AnalyzedProperty from MethodAnalysis
 * @phpstan-import-type PrintedArm from LiteralArms
 * @phpstan-import-type MapArm from LiteralArms
 * @phpstan-import-type InlineSpreadArm from InlineObjectResult
 * @phpstan-import-type ObjectSpreadArm from InlineObjectResult
 *
 * @phpstan-type Source = array{analysis: MethodAnalysis}|array{value: ValueExpressionResult}
 * @phpstan-type OrderEntry = array{key: string}|array{signature: int, name: string}
 * @phpstan-type PrintedMapArm = array{arm: InlineSpreadArm|ObjectSpreadArm, map: MapArm}
 *
 * @internal
 */
final class LiteralWrites
{
    /** @var list<Source> each recorded source, by the index its KeyWrites carry */
    private array $sources = [];

    /** @var list<KeyWrite> every named key's write, in item order */
    private array $writes = [];

    /** @var array<int, true> the sources a printed arm writes: a key only they send is the arm's to print */
    private array $armSources = [];

    /** @var list<PrintedArm> the spreads the literal's print keeps as arms, in item order */
    private array $arms = [];

    /** @var list<OrderEntry> each key's first write and each signature entry, in item order */
    private array $order = [];

    /** @var list<string> each named key written so far, once */
    private array $names = [];

    /** @var array<string, true> */
    private array $named = [];

    /** @var array<string, true> each source index and signature name already ordered, as `index:name` */
    private array $orderedSignatures = [];

    /** Every source's analysis-wide data: custom imports, carried classes and the read flags. */
    private MethodAnalysis $wide;

    /**
     * Start with no write, for the literal at $literal read in $scope, where a joined key's dropped value leaves a gap.
     */
    public function __construct(private readonly ?Node $literal = null, private readonly ?AnalysisScope $scope = null)
    {
        $this->wide = new MethodAnalysis;
    }

    /**
     * Record every key one item writes, from the analysis its value reads as: a key's write is absent or removed as
     * that analysis's row says. Its signature entries keep their place unfolded, and its flags, custom imports and
     * carried classes reach the literal as merge() hands them on.
     *
     * The item loop records a helper's, a parent's, a model filter's, an arm's and a merge call's analysis here.
     */
    public function record(MethodAnalysis $source, int $position, bool $fromMerge = false): void
    {
        $index = $this->addSource(['analysis' => $source]);
        $this->carryWide($source->customImports, $source->carried);
        $this->wide->readsCompletely = $this->wide->readsCompletely && $source->readsCompletely;
        $this->wide->holdsUnreadKeys = $this->wide->holdsUnreadKeys || $source->holdsUnreadKeys;
        $this->wide->holdsUnnamedKeys = $this->wide->holdsUnnamedKeys || $source->holdsUnnamedKeys;

        foreach ($source->writtenProperties() as $row) {
            if (JsEmitter::isIndexSignatureKey($row['name'])) {
                $this->orderSignature($index, $row['name']);

                continue;
            }

            [$absent, $removed] = self::rowFacts($row);
            $this->addWrite($row['name'], $position, $fromMerge, $absent, $removed, $index);
        }
    }

    /**
     * Record a keyed item: the value written under its name, which sets the key on every path unless the value itself
     * may be removed.
     *
     * @param  ValueExpressionResult  $result
     */
    public function recordKey(string $name, array $result, int $position): void
    {
        $this->carryWide($result['customImports'] ?? [], $result['carriedFqcns'] ?? []);
        [$absent, $removed] = self::valueFacts($result);
        $this->addWrite($name, $position, false, $absent, $removed, $this->addSource(['value' => $result]));
    }

    /**
     * Record a keyed item whose name is an index signature: one more runtime key, kept where it stands.
     *
     * @param  ValueExpressionResult  $result
     */
    public function recordSignature(string $name, array $result): void
    {
        $this->orderSignature($this->addSource(['value' => $result]), $name);
    }

    /**
     * Record the keys a spread of a value writes where it stands, each absent or removed as its value says, carrying
     * the value's custom imports and carried classes once. With $printedArm, the spread stays an arm its literal
     * prints before every key: a key only it sends is left to that print.
     *
     * @param  KeyedValues  $keys  each key and its value, no custom import on it
     * @param  ValueExpressionResult  $value  the spread's value
     */
    public function recordKeys(array $keys, array $value, int $position, bool $printedArm = false): void
    {
        $this->carryWide($value['customImports'] ?? [], $value['carriedFqcns'] ?? []);

        foreach ($keys as [$name, $result]) {
            $index = $this->addSource(['value' => $result]);
            [$absent, $removed] = self::valueFacts($result);
            $this->addWrite($name, $position, false, $absent, $removed, $index);

            if ($printedArm) {
                $this->armSources[$index] = true;
            }
        }
    }

    /**
     * Record a spread of a map, which may write any key: each key written before it may hold the map's value instead.
     *
     * @param  ValueExpressionResult  $mapValue  the value the map holds under any key, no custom import on it
     * @param  ValueExpressionResult  $value  the spread's value, whose custom imports and carried classes it spells
     */
    public function recordMapSpread(array $mapValue, array $value, int $position): void
    {
        if ($this->names === []) {
            return;
        }

        $this->carryWide($value['customImports'] ?? [], $value['carriedFqcns'] ?? []);

        foreach ($this->names as $name) {
            $this->addWrite($name, $position, false, true, false, $this->addSource(['value' => [
                ...$mapValue, 'optional' => true, 'absent' => true,
            ]]));
        }
    }

    /**
     * Record a spread the literal's print keeps as an arm, in item order: a map's after another item only where each
     * key the fold writes fits its value, and every map's joined into the last map's.
     *
     * @param  PrintedArm  $arm
     */
    public function recordArm(array $arm): void
    {
        $this->arms[] = $arm;
    }

    /**
     * Mark that the literal holds a key no name names, as an unread spread or an unnamed key leaves it.
     */
    public function holdUnnamedKeys(): void
    {
        $this->wide->holdsUnnamedKeys = true;
    }

    /**
     * The names the literal has written so far, each once: the keys a later arm or map spread finds held.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return $this->names;
    }

    /**
     * Fold the recorded writes and write each key once into an empty analysis, in the order the keys were first
     * written: its value joined from every source the fold says it can send, its presence the fold's. The arms its
     * print keeps go beside them (MethodAnalysis::spreadArms()).
     *
     * $liftsMerges is Reading::filtersBodyOf() for the body the literal is in, read at the call site.
     */
    public function applyTo(MethodAnalysis $target, bool $liftsMerges): void
    {
        if ($target->properties !== []) {
            throw new LogicException('applyTo() writes each key once, into an analysis that holds none.');
        }

        $folded = LiteralFold::fold($this->writes, $liftsMerges);

        foreach ($this->order as $entry) {
            if (isset($entry['signature'])) {
                $this->writeSignature($target, $entry['signature'], $entry['name']);

                continue;
            }

            $key = $folded[$entry['key']] ?? null;

            if ($key !== null && $key->sources !== []
                && ! array_all($key->sources, fn (int $source): bool => isset($this->armSources[$source]))) {
                $this->writeKey($target, $entry['key'], $key);
            }
        }

        $target->merge($this->wide);
        // The keys before a map's spread joined the map's value, which the arm's signature would narrow back.
        $target->setSpreadArms(array_values(array_map(
            static fn (array $arm): array => $arm['arm'],
            array_filter(
                $this->joinedArms(),
                static fn (array $arm): bool => $arm['map'] === null || ! $arm['map']['checksKeys']
                    || self::keysFitMapValue($target, $arm['map']['value']['type']),
            ),
        )));
    }

    /**
     * The presence facts a row's write carries: none where it is required, as a cast that keeps it says; else its
     * own, a `?` with neither (a declaration's) read as a key its writer may leave unset.
     *
     * @param  AnalyzedProperty  $row
     * @return array{bool, bool}
     */
    private static function rowFacts(array $row): array
    {
        if (! $row['optional']) {
            return [false, false];
        }

        $removed = $row['removed'] ?? false;

        return [($row['absent'] ?? false) || ! $removed, $removed];
    }

    /**
     * The presence facts a written value carries, as addProperty() reads them.
     *
     * @param  ValueExpressionResult  $result
     * @return array{bool, bool}
     */
    private static function valueFacts(array $result): array
    {
        if (! $result['optional']) {
            return [false, false];
        }

        $absent = $result['absent'] ?? false;

        return [$absent, ! $absent || ($result['removed'] ?? false)];
    }

    /**
     * One map's arm for several map spreads: their values joined in one union, so a class two of them hold is one
     * member, and an `unknown` one leaves the value unknown. Each spread's open readings already stand under its `{}`.
     *
     * @param  non-empty-list<PrintedMapArm>  $maps
     * @return PrintedArm
     */
    private static function joinedMaps(array $maps): array
    {
        $values = array_map(self::mapValue(...), $maps);
        $value = array_any($values, static fn (array $value): bool => $value['type'] === 'unknown')
            ? ValueResult::unknown()
            : ValueResult::unionResults($values, countMembers: true);
        $type = 'Record<string, '.$value['type'].'>';
        $result = [...$value, 'type' => $type];
        $readings = array_values(array_unique(array_merge([], ...array_map(
            static fn (array $map): array => $map['arm']['result']['openReadings'] ?? [],
            $maps,
        ))));
        unset($result['openReadings']);

        // The joined map stands where the last map does, after another item, so every key must fit its value.
        return [
            'arm' => ['type' => $type, 'result' => $readings === [] ? $result : [...$result, 'openReadings' => $readings]],
            'map' => ['value' => $value, 'checksKeys' => true, 'mayBeEmpty' => false],
        ];
    }

    /**
     * A map arm's value, with the custom imports and carried classes its spread carries, which the arm's text needs.
     *
     * @param  PrintedMapArm  $arm
     * @return ValueExpressionResult
     */
    private static function mapValue(array $arm): array
    {
        $value = $arm['map']['value'];
        $spread = $arm['arm']['result'] ?? null;

        if (isset($spread['customImports'])) {
            $value['customImports'] = $spread['customImports'];
        }

        if (isset($spread['carriedFqcns'])) {
            $value['carriedFqcns'] = $spread['carriedFqcns'];
        }

        return $value;
    }

    /**
     * Whether every named key an analysis publishes fits a map's value, a literal fitting its primitive: no `Omit<>`
     * can drop a key from the map's signature, so a key it does not fit would read as the map's value.
     */
    private static function keysFitMapValue(MethodAnalysis $analysis, string $mapValue): bool
    {
        $fits = TsTypeString::splitTopLevelUnion(VisibleGaps::stripped($mapValue)[0]);

        if (in_array('unknown', $fits, true)) {
            return true;
        }

        foreach (array_column($analysis->properties, 'type', 'name') as $name => $type) {
            $arms = JsEmitter::isIndexSignatureKey((string) $name) ? [] : TsTypeString::splitTopLevelUnion(VisibleGaps::stripped($type)[0]);

            foreach ($arms as $arm) {
                $primitive = match (true) {
                    $arm === 'true' || $arm === 'false' => 'boolean',
                    is_numeric($arm) => 'number',
                    TsTypeShape::isScalar($arm) && ! in_array($arm, ['number', 'string', 'boolean'], true) => 'string',
                    default => $arm,
                };

                if (! in_array($arm, $fits, true) && ! in_array($primitive, $fits, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * A written value without the custom imports and carried classes the literal carries once for every source.
     *
     * @param  ValueExpressionResult  $result
     * @return ValueExpressionResult
     */
    private static function withoutWide(array $result): array
    {
        unset($result['customImports'], $result['carriedFqcns']);

        return $result;
    }

    /**
     * The recorded arms with every map's taken into the last map's, in its place: `keyof` a map is every key, so the
     * print would keep the last map alone, though a key an earlier map wrote holds either map's value.
     *
     * A map that may be `[]` prints only joined into a map that is not: printed alone, its signature would also cover
     * the keys of a resource's arm beside it.
     *
     * @return array<int, PrintedArm>
     */
    private function joinedArms(): array
    {
        $maps = [];

        foreach ($this->arms as $index => $arm) {
            if ($arm['map'] !== null) {
                $maps[$index] = $arm;
            }
        }

        if (array_all($maps, static fn (array $map): bool => $map['map']['mayBeEmpty'])) {
            return array_diff_key($this->arms, $maps);
        }

        if (count($maps) < 2) {
            return $this->arms;
        }

        $arms = array_diff_key($this->arms, $maps);
        $arms[(int) array_key_last($maps)] = self::joinedMaps(array_values($maps));
        ksort($arms);

        return $arms;
    }

    /**
     * Add a source and say its index.
     *
     * @param  Source  $source
     */
    private function addSource(array $source): int
    {
        $this->sources[] = $source;

        return count($this->sources) - 1;
    }

    /**
     * Carry a source's custom imports and carried classes onto the literal, once.
     *
     * @param  array<string, list<string>>  $customImports
     * @param  array<string, list<class-string>>  $carried
     */
    private function carryWide(array $customImports, array $carried): void
    {
        if ($customImports === [] && $carried === []) {
            return;
        }

        $carrier = new MethodAnalysis(customImports: $customImports);
        $carrier->carry($carried);
        $this->wide->merge($carrier);
    }

    /**
     * Add one named key's write, ordering the key at its first.
     */
    private function addWrite(string $name, int $position, bool $fromMerge, bool $absent, bool $removed, int $source): void
    {
        if (! isset($this->named[$name])) {
            $this->named[$name] = true;
            $this->names[] = $name;
            $this->order[] = ['key' => $name];
        }

        $this->writes[] = new KeyWrite($name, $position, $fromMerge, $absent, $removed, $source);
    }

    /**
     * Order a source's entries under one signature name, once.
     */
    private function orderSignature(int $source, string $name): void
    {
        if (! isset($this->orderedSignatures[$source.':'.$name])) {
            $this->orderedSignatures[$source.':'.$name] = true;
            $this->order[] = ['signature' => $source, 'name' => $name];
        }
    }

    /**
     * Write one folded key once: its one source's row and maps, or every source's joined.
     */
    private function writeKey(MethodAnalysis $target, string $name, FoldedKey $key): void
    {
        if (count($key->sources) === 1) {
            $source = $this->sources[$key->sources[0]];

            if (isset($source['value'])) {
                $target->addProperty($name, self::withoutWide($source['value']));
            } else {
                $target->appendSlice($source['analysis']->sliceOf($name), keepsHeldObjects: false);
            }
        } else {
            $target->appendSlice($this->joinedSlice($name, $key->sources), keepsHeldObjects: false);
        }

        $last = array_key_last($target->properties);

        if ($last !== null) {
            $target->properties[$last] = MethodAnalysis::withPresence(
                $target->properties[$last], $key->optional, $key->absent, $key->removed,
            );
        }
    }

    /**
     * Write a signature entry where it stood: a keyed item's value, or every row a source holds under the name.
     */
    private function writeSignature(MethodAnalysis $target, int $index, string $name): void
    {
        $source = $this->sources[$index];

        if (isset($source['value'])) {
            $target->addSignatureEntry($name, self::withoutWide($source['value']));

            return;
        }

        $target->appendSlice($source['analysis']->sliceOf($name), keepsHeldObjects: false);
    }

    /**
     * One key's row and maps, joined from each source's slice as a branch join joins them (BranchJoin::joinInto()):
     * the first source's untyped value kept, as PHP may still send it, and every class kept apart and imported. The
     * caller sets the fold's presence.
     *
     * @param  list<int>  $sources
     */
    private function joinedSlice(string $name, array $sources): MethodAnalysis
    {
        $slices = [];

        foreach ($sources as $index) {
            $source = $this->sources[$index];

            if (isset($source['value'])) {
                $slice = new MethodAnalysis;
                $slice->addProperty($name, self::withoutWide($source['value']));
                $slices[] = $slice;
            } else {
                $slices[] = $source['analysis']->sliceOf($name);
            }
        }

        $joined = new MethodAnalysis;
        BranchJoin::joinInto($joined, $slices, dropsUntypedBranches: true, keepsFirstUntyped: true, keepsClassesApart: true);

        return $this->withDroppedGap($joined, $slices);
    }

    /**
     * In the gap mode, a joined key the join typed without a later source's untyped value, which may not be written,
     * with a gap arm in that value's place; out of it, the key as it is.
     *
     * @param  list<MethodAnalysis>  $slices
     */
    private function withDroppedGap(MethodAnalysis $joined, array $slices): MethodAnalysis
    {
        $property = $joined->properties[0] ?? null;

        if ($property === null || $this->literal === null || $this->scope === null || $property['type'] === 'unknown'
            || ! resolve(AnalysisMemo::class)->showsGaps()) {
            return $joined;
        }

        $dropped = array_any(
            array_slice($slices, 1),
            static fn (MethodAnalysis $slice): bool => in_array('unknown', array_column($slice->properties, 'type'), true),
        );

        if ($dropped) {
            $id = DroppedUnionArms::gap($this->scope, 'fold-join', $this->literal, $property['type']);
            $joined->properties[0]['type'] = VisibleGaps::withArm($property['type'], $id);
        }

        return $joined;
    }
}
