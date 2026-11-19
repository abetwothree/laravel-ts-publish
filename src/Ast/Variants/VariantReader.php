<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Variants;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Fold\LiteralWrites;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Effects\InterveningWrites;
use AbeTwoThree\LaravelTsPublish\Ast\Effects\PropertyHooks;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\ReadPath;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
use Closure;
use Illuminate\Support\Facades\Config;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Identifier;
use PhpParser\NodeFinder;

/**
 * Reads which arm each key of one array literal takes for every value a sibling key sends, as groups of keys that
 * follow one discriminant.
 *
 * A discriminant is a required key one keyed item alone writes, whose value is a plain read with a finite set of
 * values. A dependent is another such key whose ternary, `match`, when() or unless() that read decides for every value,
 * taking arms that differ between two values. Each arm is typed by the handler that typed the key and read apart from
 * the flat rows, which stay as they are.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type DiscriminantValueList from DiscriminantValue
 *
 * @phpstan-type Discriminant = array{name: string, read: ReadPath, values: DiscriminantValueList}
 * @phpstan-type SoleKey = array{string, Expr, int}
 * @phpstan-type Picks = array{arms: list<ConditionalArm>, drops: bool}
 * @phpstan-type ArmFingerprint = array{type: string, optional: bool, wraps: list<string>, reads: list<string>}
 *
 * @internal
 */
final class VariantReader
{
    /** How many levels of arms one value is selected through, where a selected arm's body picks between arms again. */
    private const int LEVELS = 8;

    /** @var array<int, ConditionalArms|null> the arms each expression hands over, by its object id, read once */
    private array $arms = [];

    /** @var list<string> the picked keys with an arm that throws, whose guards decide which values the group sends */
    private array $droppers = [];

    /**
     * Read the groups of one discriminant.
     *
     * @param  Discriminant  $discriminant
     * @param  Closure(Expr): ?ConditionalArms  $armsOf
     * @param  Closure(ValueExpressionResult): ValueExpressionResult  $arrayValue
     */
    private function __construct(
        private readonly array $discriminant,
        private readonly AnalysisScope $scope,
        private readonly Closure $armsOf,
        private readonly Closure $arrayValue,
    ) {}

    /**
     * The groups a literal's fold holds, or null where none holds.
     *
     * ResourceAstAnalyzer::analyzeReturnArrayItems() reads every literal through it once the fold is written.
     *
     * @param  Array_  $array  the literal, with the items its item loop read
     * @param  array<int, string>  $keyNames  each keyed item's index => the key it writes
     * @param  bool  $liftsMerges  whether `filter()` lifts the literal's merge calls, as the fold was told
     * @param  MethodAnalysis  $analysis  the literal's fold
     * @param  Closure(Expr): ?ConditionalArms  $armsOf  the arms the handler that typed an expression hands over
     * @param  Closure(ValueExpressionResult): ValueExpressionResult  $arrayValue  what the literal's reading sends for
     *                                                                             a value one of its keys holds
     */
    public static function read(
        Array_ $array,
        array $keyNames,
        LiteralWrites $writes,
        bool $liftsMerges,
        MethodAnalysis $analysis,
        AnalysisScope $scope,
        Closure $armsOf,
        Closure $arrayValue,
    ): ?LiteralVariants {
        if (! self::maySplit($array, $keyNames) || self::holdsSignature($analysis)) {
            return null;
        }

        $keys = self::soleKeys($array, $keyNames, $writes);
        $groups = [];
        $owned = [];

        foreach (self::discriminants($keys, $writes, $analysis, $scope) as $discriminant) {
            $reader = new self($discriminant, $scope, $armsOf, $arrayValue);
            // The flat rows already read every arm, so no drop, warning or audit entry of these reads outlives them.
            $group = DroppedUnionArms::provisional(
                static fn (): ?VariantGroup => $reader->group($keys, $owned, $analysis),
                static fn (): bool => false,
            );

            if ($group !== null && ! self::rewritten(
                $array,
                $keys,
                [...$group->keys, ...$reader->droppers],
                $discriminant['read'],
                $scope,
            )) {
                $groups[] = $group;
                $owned += array_fill_keys($group->keys, true);
            }
        }

        return $groups === [] ? null : new LiteralVariants(
            $groups,
            $writes,
            $liftsMerges,
            VariantGroup::fingerprint($analysis, array_column($analysis->writtenProperties(), 'name')),
            $analysis->casts,
        );
    }

    /**
     * Whether the literal has two keyed items, one of whose values picks between arms.
     *
     * @param  array<int, string>  $keyNames
     */
    private static function maySplit(Array_ $array, array $keyNames): bool
    {
        return count($keyNames) > 1
            && array_any(array_keys($keyNames), static fn (int $index): bool => self::splits($array->items[$index]->value));
    }

    /**
     * Whether an expression picks between arms by a guard: a ternary, a `match`, or a when() or unless() call.
     */
    private static function splits(Expr $expr): bool
    {
        return $expr instanceof Ternary
            || $expr instanceof Match_
            || ($expr instanceof MethodCall && $expr->name instanceof Identifier
                && in_array($expr->name->toLowerString(), ['when', 'unless'], true));
    }

    /**
     * Whether the fold holds an index signature, whose many keys a variant cannot name.
     */
    private static function holdsSignature(MethodAnalysis $analysis): bool
    {
        return array_any(
            $analysis->properties,
            static fn (array $row): bool => JsEmitter::isIndexSignatureKey($row['name']),
        );
    }

    /**
     * Each key one keyed item writes and nothing else may, beside that item's value and index, in item order.
     *
     * @param  array<int, string>  $keyNames
     * @return list<SoleKey>
     */
    private static function soleKeys(Array_ $array, array $keyNames, LiteralWrites $writes): array
    {
        $keys = [];

        foreach ($keyNames as $index => $name) {
            if ($writes->soleWriter($name) === $index) {
                $keys[] = [$name, $array->items[$index]->value, $index];
            }
        }

        return $keys;
    }

    /**
     * Each sole key that may be a discriminant, in item order: its row required, its value a plain read holding what
     * its row prints rather than a class-declared type, and two or more values it may send.
     *
     * @param  list<SoleKey>  $keys
     * @return list<Discriminant>
     */
    private static function discriminants(
        array $keys,
        LiteralWrites $writes,
        MethodAnalysis $analysis,
        AnalysisScope $scope,
    ): array {
        $discriminants = [];

        foreach ($keys as [$name, $value]) {
            $sole = $writes->soleValue($name);
            $read = self::readOf($value, $sole['sendsEnumScalar'] ?? false);

            // A guard tests the value PHP holds, which a class-declared type does not describe.
            if ($read === null || ($sole['typedByClass'] ?? false) || ! self::required($analysis, $name)) {
                continue;
            }

            $values = DiscriminantValues::of($analysis, $name, $scope->casesAsLiterals) ?? [];

            if (count($values) > 1 && ! self::readsHook($read, $scope)) {
                $discriminants[] = ['name' => $name, 'read' => $read, 'values' => $values];
            }
        }

        return $discriminants;
    }

    /**
     * Whether a read runs a get hook at any step, on `$this` or on an object a step reaches, so two reads of it may
     * send different values.
     */
    private static function readsHook(ReadPath $read, AnalysisScope $scope): bool
    {
        return PropertyHooks::of($scope)->getHooks($read, $scope->declaringClass) !== [];
    }

    /**
     * Whether the fold writes a key on every path, with a value nothing removes.
     */
    private static function required(MethodAnalysis $analysis, string $name): bool
    {
        return array_any(
            $analysis->writtenProperties(),
            static fn (array $row): bool => $row['name'] === $name && ! $row['optional'],
        );
    }

    /**
     * The read holding the values a key sends: its own, or the enum read whose `->value` or `->name` it sends, as its
     * values are that enum's cases; null where it sends a case's scalar any other way, which no read holds.
     */
    private static function readOf(Expr $value, bool $sendsEnumScalar): ?ReadPath
    {
        if (! $sendsEnumScalar) {
            return ReadPath::of($value);
        }

        $sendsMember = ($value instanceof PropertyFetch || $value instanceof NullsafePropertyFetch)
            && $value->name instanceof Identifier
            && in_array($value->name->toString(), ['value', 'name'], true);

        return $sendsMember ? ReadPath::of($value->var) : null;
    }

    /**
     * Whether an item from the first of the group's keys and droppers to the last may write the discriminant's read, or
     * a read it is a member of, so a guard may test another value than the discriminant sends (InterveningWrites).
     *
     * @param  list<SoleKey>  $keys
     * @param  list<string>  $spanned  the group's keys and the droppers
     */
    private static function rewritten(
        Array_ $array,
        array $keys,
        array $spanned,
        ReadPath $read,
        AnalysisScope $scope,
    ): bool {
        $at = array_column(array_filter($keys, static fn (array $key): bool => in_array($key[0], $spanned, true)), 2);

        if ($at === []) {
            return false;
        }

        return InterveningWrites::reach(
            array_values(array_slice($array->items, min($at), max($at) - min($at) + 1)),
            $read,
            $scope,
        );
    }

    /**
     * The values no picked arm throws for, by their index: PHP sends nothing for a value an arm throws for.
     *
     * @param  DiscriminantValueList  $values
     * @param  array<string, Picks>  $picks
     * @return array<int, DiscriminantValue>
     */
    private static function sentValues(array $values, array $picks): array
    {
        return array_filter(
            $values,
            static fn (int $index): bool => ! array_any($picks, static fn (array $picked): bool => $picked['arms'][$index]->throws),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Whether two sent values take arms that differ in type text, presence or the classes their tokens name.
     *
     * @param  Picks  $picked
     * @param  array<int, DiscriminantValue>  $sent
     */
    private static function differs(array $picked, array $sent): bool
    {
        $taken = array_map(
            static fn (int $index): string => serialize(self::armFingerprint($picked['arms'][$index]->result)),
            array_keys($sent),
        );

        return count(array_unique($taken)) > 1;
    }

    /**
     * Whether every arm a dependent takes for a sent value stands: read with no arm left out, and typed with no gap.
     *
     * @param  Picks  $picked
     * @param  array<int, DiscriminantValue>  $sent
     */
    private static function stands(array $picked, array $sent): bool
    {
        return ! $picked['drops'] && array_all(array_keys($sent), static function (int $index) use ($picked): bool {
            $type = $picked['arms'][$index]->result['type'] ?? null;

            return $type === null || ($type !== 'unknown' && ! VisibleGaps::holds($type));
        });
    }

    /**
     * One variant per distinct set of arms the sent values take, in the order of each one's first value.
     *
     * @param  array<string, Picks>  $dependents
     * @param  array<int, DiscriminantValue>  $sent
     * @return list<Variant>
     */
    private static function variants(array $dependents, array $sent): array
    {
        $values = [];
        $arms = [];

        foreach ($sent as $index => $value) {
            $taken = array_map(static fn (array $picked): ?array => $picked['arms'][$index]->result, $dependents);
            $key = serialize(array_map(self::armFingerprint(...), $taken));
            $values[$key][] = $value;
            $arms[$key] ??= $taken;
        }

        return array_map(static fn (string $key): Variant => new Variant($values[$key], $arms[$key]), array_keys($values));
    }

    /**
     * What an arm sends, as a row's fingerprint beside the classes its tokens name, or null where it removes its key:
     * two arms spelling one name for two classes, or one enum wrapped and one read directly, publish apart.
     *
     * @param  ValueExpressionResult|null  $result
     * @return ArmFingerprint|null
     */
    private static function armFingerprint(?array $result): ?array
    {
        if ($result === null) {
            return null;
        }

        $wraps = [
            $result['enumFqcn'] ?? null,
            ...$result['embeddedEnumResourceFqcns'] ?? [],
            ...$result['multiEnumResourceFqcns'] ?? [],
        ];
        $reads = [
            $result['directEnumFqcn'] ?? null,
            $result['resourceFqcn'] ?? null,
            $result['modelFqcn'] ?? null,
            ...$result['embeddedEnumFqcns'] ?? [],
            ...$result['embeddedModelFqcns'] ?? [],
            ...$result['embeddedResourceFqcns'] ?? [],
        ];

        // Only the tolki package prints an enum resource apart from a read of its enum, as `AsEnum<typeof X>`.
        if (! Config::boolean('ts-publish.enums.use_tolki_package')) {
            [$wraps, $reads] = [[], [...$reads, ...$wraps]];
        }

        return [
            'type' => $result['type'],
            'optional' => $result['optional'],
            'wraps' => self::classList($wraps),
            'reads' => self::classList($reads),
        ];
    }

    /**
     * The classes named, in token order and as often as named, since that order binds each token to its class.
     *
     * @param  list<class-string|null>  $fqcns
     * @return list<class-string>
     */
    private static function classList(array $fqcns): array
    {
        return array_values(array_filter($fqcns));
    }

    /**
     * The group of keys this discriminant decides, or null where none follows it or an arm one takes cannot stand,
     * recording its droppers.
     *
     * @param  list<SoleKey>  $keys  the literal's sole keys, in item order
     * @param  array<string, true>  $owned  the keys an earlier discriminant's group holds
     */
    private function group(array $keys, array $owned, MethodAnalysis $analysis): ?VariantGroup
    {
        $picks = [];

        foreach ($keys as [$name, $value]) {
            $picked = isset($owned[$name]) || ! $this->mayFollow($value) ? null : $this->picks($value);

            if ($picked === null) {
                continue;
            }

            $picks[$name] = $picked;

            if (array_any($picked['arms'], static fn (ConditionalArm $arm): bool => $arm->throws)) {
                $this->droppers[] = $name;
            }
        }

        $sent = self::sentValues($this->discriminant['values'], $picks);
        $dependents = array_filter($picks, static fn (array $picked): bool => self::differs($picked, $sent));

        if ($dependents === [] || ! array_all($dependents, static fn (array $picked): bool => self::stands($picked, $sent))) {
            return null;
        }

        $names = array_values(array_filter(
            array_column($keys, 0),
            fn (string $name): bool => $name === $this->discriminant['name'] || isset($dependents[$name]),
        ));

        return new VariantGroup(
            $this->discriminant['name'],
            $names,
            VariantGroup::fingerprint($analysis, $names),
            self::variants($dependents, $sent),
        );
    }

    /**
     * Whether a value may follow the discriminant: it picks between arms, and reads the discriminant's read somewhere.
     */
    private function mayFollow(Expr $value): bool
    {
        $read = $this->discriminant['read'];

        return self::splits($value) && new NodeFinder()->findFirst($value, static function (Node $node) use ($read): bool {
            $path = $node instanceof Expr ? ReadPath::of($node) : null;

            return $path !== null && ($path->same($read) || $path->memberOf($read) !== null);
        }) !== null;
    }

    /**
     * The arm a key's value takes for each value the discriminant sends, its result as the literal's reading sends it,
     * and whether reading them left an arm out; null where a value leaves the first guard undecided.
     *
     * @return Picks|null
     */
    private function picks(Expr $value): ?array
    {
        $dropped = DroppedUnionArms::dropped();
        $arms = [];

        foreach ($this->discriminant['values'] as $sent) {
            $arm = $this->selected($this->armsFor($value), $sent);

            if ($arm === null) {
                return null;
            }

            $result = $arm->result;
            $arms[] = $result === null
                ? $arm
                : new ConditionalArm($arm->guard, $arm->body, ($this->arrayValue)($result), $arm->throws);
        }

        return ['arms' => $arms, 'drops' => DroppedUnionArms::dropped() !== $dropped];
    }

    /**
     * The arm a value selects, selected again in its body while that picks between arms, up to LEVELS deep; a level
     * that cannot decide keeps the arm above it. Null where the first level hands over no arms or cannot decide.
     */
    private function selected(?ConditionalArms $arms, DiscriminantValue $sent, int $level = 1): ?ConditionalArm
    {
        $arm = $arms?->select(fn (Expr $guard): ?bool => ConditionTruth::decide(
            $guard,
            $this->discriminant['read'],
            $sent,
            $this->scope,
        ));

        if ($arm?->body === null || $level === self::LEVELS) {
            return $arm;
        }

        return $this->selected($this->armsFor($arm->body), $sent, $level + 1) ?? $arm;
    }

    /**
     * The arms the handler that typed an expression hands over, read once.
     */
    private function armsFor(Expr $expr): ?ConditionalArms
    {
        $id = spl_object_id($expr);

        if (! array_key_exists($id, $this->arms)) {
            $this->arms[$id] = ($this->armsOf)($expr);
        }

        return $this->arms[$id];
    }
}
