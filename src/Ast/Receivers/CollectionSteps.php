<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Receivers;

use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\Int_;
use ReflectionMethod;

/**
 * What a collection step does to the items and the keys of the collection it is called on: whether it keeps the
 * items, keeps their keys, leaves them out of 0..n-1, or hands back one item or other values. The receiver resolvers
 * and the collection handlers walk a chain and read each step here, so they agree on each step.
 *
 * @phpstan-type ChainStep array{name: string, node: MethodCall}
 * @phpstan-type StepWalk array{keyed: bool, map: MethodCall|null, keys: bool}
 *
 * @internal
 */
final class CollectionSteps
{
    /** The collection methods that hand back the same items, or a leading run of them, under the keys they had. */
    private const array SAME_ITEMS = [
        'load', 'loadMissing', 'loadCount', 'loadMorph', 'loadMorphCount', 'loadAggregate', 'loadSum', 'loadMax',
        'loadMin', 'loadAvg', 'loadExists', 'each', 'tap', 'collect', 'toBase', 'lazy', 'makeHidden', 'mergeHidden',
        'makeVisible', 'mergeVisible', 'setVisible', 'setHidden', 'append', 'setAppends', 'withoutAppends',
        'withRelationshipAutoloading', 'takeUntil', 'takeWhile',
    ];

    /** The SAME_ITEMS steps that drop attributes or appends a model serializes, which its interface names. */
    private const array HIDES_ATTRIBUTES = [
        'makeHidden', 'mergeHidden', 'setHidden', 'setVisible', 'mergeVisible', 'setAppends', 'withoutAppends',
    ];

    /** The collection methods that hand back some of the same items, under keys they may leave out of 0..n-1. */
    private const array SAME_ITEMS_KEYED = [
        'filter', 'reject', 'where', 'whereStrict', 'whereIn', 'whereInStrict', 'whereNotIn', 'whereNotInStrict',
        'whereNull', 'whereNotNull', 'whereBetween', 'whereNotBetween', 'whereInstanceOf', 'unique', 'uniqueStrict',
        'sortBy', 'sortByDesc', 'sort', 'sortDesc', 'sortKeys', 'sortKeysDesc', 'sortKeysUsing', 'keyBy', 'skip',
        'skipUntil', 'skipWhile', 'slice', 'reverse', 'forPage', 'take', 'diff', 'intersect', 'except', 'only',
        'fresh',
    ];

    /** The collection steps that hand back other values than the items, though their docblocks may name none. */
    private const array REPLACES_ITEMS = [
        'pluck', 'map', 'mapWithKeys', 'mapToDictionary', 'mapToGroups', 'mapSpread', 'mapInto', 'flatMap', 'keys',
        'flip', 'countBy', 'zip', 'collapse', 'flatten', 'pad', 'partition', 'groupBy', 'chunk', 'chunkWhile', 'split',
        'splitIn', 'sliding', 'crossJoin', 'combine', 'transform', 'select', 'dot', 'undot',
    ];

    /** The collection steps that hand back the same items re-indexed 0..n-1. */
    private const array RELISTS = ['values', 'shuffle'];

    /** The collection steps that add the items they are given to the same items. */
    private const array ADDS_ITEMS = ['merge', 'concat'];

    /**
     * The REPLACES_ITEMS steps whose items stay the kind they were: lists of the same items, or, for `flatten()`,
     * `dot()` and `undot()`, the same models, which none of them opens.
     */
    private const array KEEPS_ITEM_KIND = [
        'groupBy', 'chunk', 'chunkWhile', 'split', 'splitIn', 'partition', 'sliding', 'flatten', 'dot', 'undot',
    ];

    /** The SAME_ITEMS_KEYED steps Eloquent\Collection overrides to re-index the models it keeps, for most arguments. */
    private const array ELOQUENT_RELISTS = ['only', 'except', 'unique', 'uniqueStrict', 'diff', 'intersect'];

    /** The steps that hand back one item: an `element` throws where there is none, a `nullableElement` sends null. */
    private const array ELEMENT_STEPS = [
        'first' => 'nullableElement', 'last' => 'nullableElement', 'firstWhere' => 'nullableElement',
        'find' => 'nullableElement', 'get' => 'nullableElement', 'sole' => 'element', 'firstOrFail' => 'element',
    ];

    /** The element steps that hand back the `$default` they are passed where no item matches. */
    private const array DEFAULTED_ELEMENT_STEPS = ['first', 'last'];

    /**
     * A chain of method calls as its steps, root first, and the expression it is rooted at, which a call by a dynamic
     * name also ends the walk as.
     *
     * @return array{0: list<ChainStep>, 1: Expr}
     */
    public static function chain(MethodCall $call): array
    {
        $steps = [];
        $node = $call;

        while ($node instanceof MethodCall && $node->name instanceof Identifier) {
            $steps[] = ['name' => $node->name->toString(), 'node' => $node];
            $node = $node->var;
        }

        return [array_reverse($steps), $node];
    }

    /**
     * Walk a chain's steps after its root as both collection handlers read them: `all()` and a step keeping the keys
     * leave them, another step keeping the items moves them as keyedAfter() says, and at most one `map()` maps them.
     *
     * One `keys()` before it re-lists the items as their keys, where no step moved those yet, and a step that hides a
     * model's attributes declines. $other reads any other step before `keys()`, handing back whether the keys are out
     * of 0..n-1 after it, or null to decline, as a chain with no $other does. $eloquent: an Eloquent\Collection.
     *
     * @param  list<ChainStep>  $steps
     * @param  (Closure(ChainStep, bool): ?bool)|null  $other
     * @return StepWalk|null
     */
    public static function walk(array $steps, bool $eloquent, ?Closure $other = null): ?array
    {
        $keyed = false;
        $map = null;
        $keys = false;

        foreach ($steps as $step) {
            $name = $step['name'];

            if (in_array($name, self::HIDES_ATTRIBUTES, true)) {
                return null;
            }

            if ($name === 'all' || self::keepsKeys($name)) {
                continue;
            }

            if (self::keepsItems($name)) {
                $keyed = self::keyedAfter($step['node'], $name, $keyed, $eloquent);

                continue;
            }

            if ($name === 'map' && $map === null) {
                $map = $step['node'];

                continue;
            }

            // A step that moved the keys, such as keyBy(), leaves keys() a type it cannot read.
            if ($name === 'keys' && ! $keys && ! $keyed && $map === null) {
                $keys = true;

                continue;
            }

            $after = $other === null || $keys ? null : $other($step, $keyed);

            if ($after === null) {
                return null;
            }

            $keyed = $after;
        }

        return ['keyed' => $keyed, 'map' => $map, 'keys' => $keys];
    }

    /**
     * What a step that hands back one item hands back: `element`, or `nullableElement` where none may match; null for
     * any other step.
     *
     * @return 'element'|'nullableElement'|null
     */
    public static function elementStep(string $method): ?string
    {
        return self::ELEMENT_STEPS[$method] ?? null;
    }

    /**
     * Whether a `first()` or `last()` call hands back an item or null: not where it passes the `$default` Laravel sends
     * when no item matches, or its arguments cannot be read.
     */
    public static function elementCall(MethodCall|NullsafeMethodCall|StaticCall $call, string $method): bool
    {
        if (! in_array($method, self::DEFAULTED_ELEMENT_STEPS, true)) {
            return false;
        }

        $arguments = CallArguments::for($call, new ReflectionMethod(Collection::class, $method));

        return ! $arguments->hasUnpack() && $arguments->named('default') === null;
    }

    /**
     * Whether a chain's last step is an argless `toArray()` after a `map()`: it hands back the mapped items as `all()`
     * does, keys and all, converting only an Arrayable item, so filter() reaches each resource a callback returns.
     *
     * @param  list<ChainStep>  $steps
     */
    public static function endsInMappedToArray(array $steps): bool
    {
        $last = $steps === [] ? null : $steps[count($steps) - 1];

        return $last !== null
            && $last['name'] === 'toArray'
            && ! $last['node']->isFirstClassCallable()
            && $last['node']->getArgs() === []
            && array_any(array_slice($steps, 0, -1), static fn (array $step): bool => $step['name'] === 'map');
    }

    /**
     * Whether a collection step hands back the same items, or a leading run of them, under the keys they had.
     */
    public static function keepsKeys(string $method): bool
    {
        return in_array($method, self::SAME_ITEMS, true);
    }

    /**
     * Whether a collection step hands back some of the same items, under their keys or re-indexed.
     */
    public static function keepsItems(string $method): bool
    {
        return self::keepsKeys($method) || in_array($method, self::RELISTS, true) || in_array($method, self::SAME_ITEMS_KEYED, true);
    }

    /**
     * Whether a collection step adds the items it is given, so it keeps the items' kind where those are of it.
     */
    public static function addsItems(string $method): bool
    {
        return in_array($method, self::ADDS_ITEMS, true);
    }

    /**
     * Whether a collection step provably hands back items of another kind than its own, as `map()` and `pluck()` do;
     * a list of lists of its items, as `groupBy()` hands back, does not.
     */
    public static function changesItems(string $method): bool
    {
        return in_array($method, self::REPLACES_ITEMS, true) && ! in_array($method, self::KEEPS_ITEM_KIND, true);
    }

    /**
     * Whether a step that may move a collection's keys leaves them out of 0..n-1: `values()` re-indexes, as an
     * Eloquent\Collection's ELOQUENT_RELISTS steps do for most arguments; a front `take()` or a bare `sortKeys()`
     * keeps a list a list, while a callback, a flag or any other step may reorder or gap it.
     *
     * $keyed says the keys already are out of 0..n-1, and $eloquent that the collection is an Eloquent\Collection.
     */
    public static function keyedAfter(MethodCall|NullsafeMethodCall $call, string $method, bool $keyed, bool $eloquent): bool
    {
        return match (true) {
            in_array($method, self::RELISTS, true) => false,
            $method === 'take' => $keyed || ! self::takesFromFront($call),
            $method === 'sortKeys' => $keyed || $call->getArgs() !== [],
            in_array($method, self::ELOQUENT_RELISTS, true) && $eloquent => self::eloquentKeyedAfter($call, $method, $keyed),
            default => true,
        };
    }

    /**
     * Whether an Eloquent\Collection's own `only()`, `except()`, `unique()`, `diff()` or `intersect()` leaves the keys
     * out of 0..n-1: each re-indexes the models it keeps, but for `only(null)` and `except(null)`, which keep every
     * key, and a `unique()` by a key, which Support\Collection's own runs.
     */
    public static function eloquentKeyedAfter(MethodCall|NullsafeMethodCall $call, string $method, bool $keyed): bool
    {
        if ($method === 'diff' || $method === 'intersect') {
            return false;
        }

        $arguments = CallArguments::for($call, new ReflectionMethod(EloquentCollection::class, $method));
        $filters = $method === 'only' || $method === 'except';
        $passed = $arguments->named($filters ? 'keys' : 'key')?->value;
        $nothing = $passed === null || ($passed instanceof ConstFetch && $passed->name->toLowerString() === 'null');

        return match (true) {
            $arguments->hasUnpack() => true,
            $filters => $nothing && $keyed,
            default => ! $nothing,
        };
    }

    /**
     * Whether a chain of calls takes a step that hands back other values than the items, as `pluck()` or `map()` does.
     */
    public static function replacesItems(Expr $value): bool
    {
        while ($value instanceof MethodCall || $value instanceof NullsafeMethodCall) {
            if ($value->name instanceof Identifier && in_array($value->name->toString(), self::REPLACES_ITEMS, true)) {
                return true;
            }

            $value = $value->var;
        }

        return false;
    }

    /**
     * Whether a `take()` call takes a literal count from the front, where a list stays a list.
     */
    public static function takesFromFront(MethodCall|NullsafeMethodCall $call): bool
    {
        $arguments = CallArguments::for($call, new ReflectionMethod(Collection::class, 'take'));

        return $arguments->passedCount() === 1 && ! $arguments->hasUnpack() && $arguments->named('limit')?->value instanceof Int_;
    }
}
