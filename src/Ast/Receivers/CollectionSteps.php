<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Receivers;

use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\Int_;
use ReflectionMethod;

/**
 * What a collection step does to the items and the keys of the collection it is called on: whether it keeps the
 * items, keeps their keys, leaves them out of 0..n-1, or hands back other values. The receiver resolver and the
 * relation-chain and `collect()` pipeline handlers read a step's keys here, so they agree on each step.
 *
 * @internal
 */
final class CollectionSteps
{
    /** The collection methods that hand back the same items, or a leading run of them, under the keys they had. */
    private const array SAME_ITEMS = [
        'load', 'loadMissing', 'loadCount', 'loadMorph', 'loadMorphCount', 'loadAggregate', 'loadSum', 'loadMax',
        'loadMin', 'loadAvg', 'loadExists', 'each', 'tap', 'collect', 'toBase', 'makeHidden', 'mergeHidden',
        'makeVisible', 'mergeVisible', 'setVisible', 'setHidden', 'append', 'setAppends', 'withoutAppends',
        'withRelationshipAutoloading', 'takeUntil', 'takeWhile',
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

    /** The SAME_ITEMS_KEYED steps Eloquent\Collection overrides to re-index the models it keeps, for most arguments. */
    private const array ELOQUENT_RELISTS = ['only', 'except', 'unique', 'uniqueStrict', 'diff', 'intersect'];

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
        return self::keepsKeys($method) || $method === 'values' || in_array($method, self::SAME_ITEMS_KEYED, true);
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
            $method === 'values' => false,
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
