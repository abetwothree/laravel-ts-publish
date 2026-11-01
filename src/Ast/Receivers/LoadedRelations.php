<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Receivers;

use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesFilteredRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;

/**
 * The relations a `load()` or `loadMissing()` call adds to what a model serializes, from its literal argument read as
 * Eloquent\Builder::prepareNestedWithRelationships() reads it: names, lists, dotted paths, nested arrays, closures and
 * `:col,col` lists.
 *
 * Each member is typed as the relation's read publishes it, under the key toArray() writes; one whose payload no type
 * here admits is left out, so its key goes unclaimed.
 *
 * @phpstan-type Constraint = list<string>|ClosureExpr|ArrowFunction|false|null
 * @phpstan-type RelationPaths = array<string, list<Constraint>>
 * @phpstan-type RelationTree = array<string, array{constraints: list<Constraint>, children: array<string, mixed>}>
 * @phpstan-type LoadedMembers = array{type: string, models: list<class-string>}
 *
 * @internal
 */
final class LoadedRelations
{
    use ResolvesFilteredRelationTypes;

    /** The constraint calls that keep each related row and its columns: they order, count, or load more under it. */
    private const array KEEPS_ROWS = [
        'orderBy', 'orderByDesc', 'orderByRaw', 'latest', 'oldest', 'reorder', 'reorderDesc', 'inRandomOrder', 'with',
        'withCount', 'withSum', 'withAvg', 'withMin', 'withMax', 'withExists', 'withAggregate', 'withTrashed',
        'withoutGlobalScope', 'withoutGlobalScopes', 'without', 'withOnly',
    ];

    /** The constraint calls, besides each `where…()`, that may drop a related row but keep the columns of the rest. */
    private const array FILTERS_ROWS = [
        'limit', 'take', 'skip', 'offset', 'forPage', 'has', 'orHas', 'doesntHave', 'orDoesntHave', 'onlyTrashed',
        'withoutTrashed', 'having', 'orHaving', 'havingRaw', 'orHavingRaw', 'havingBetween',
    ];

    /** The Model methods that write a loaded relation into the array Inertia and json_encode() send. */
    private const array SERIALIZERS = ['toArray', 'jsonSerialize', 'relationsToArray', 'getArrayableRelations', 'getArrayableItems'];

    /**
     * The `{ key: Type; … }` object a `load()` or `loadMissing()` call on a model adds, with the models its tokens
     * name in order; null where the argument is not a literal, Laravel throws on it, or it adds no member this types.
     *
     * @param  class-string<Model>  $model
     * @param  bool  $unsaved  no row may back the model yet, so a to-one relation may load as null
     * @return LoadedMembers|null
     */
    public function members(string $model, MethodCall|NullsafeMethodCall $call, bool $unsaved = false): ?array
    {
        $paths = $this->relationPaths($call);
        $missing = $call->name instanceof Identifier && $call->name->toLowerString() === 'loadmissing';

        return $paths === null ? null : $this->membersOf($model, $this->tree($paths), $unsaved, $missing ? [] : null);
    }

    /**
     * Each relation path a call names, with the constraints written for it.
     *
     * @return RelationPaths|null
     */
    private function relationPaths(MethodCall|NullsafeMethodCall $call): ?array
    {
        if ($call->isFirstClassCallable()) {
            return null;
        }

        $args = $call->getArgs();
        $paths = [];

        foreach ($args as $arg) {
            if ($arg->unpack || ($arg->name !== null && ($arg->name->toString() !== 'relations' || count($args) > 1))) {
                return null;
            }
        }

        $first = $args[0]->value ?? null;

        if ($first instanceof Array_) {
            if (! $this->prepare($first, '', $paths)) {
                return null;
            }
        } elseif ($first instanceof String_) {
            // A string argument is read with every other argument, as func_get_args() hands them on.
            foreach ($args as $arg) {
                if (! $arg->value instanceof String_ || ! $this->addNamed($arg->value->value, '', $paths)) {
                    return null;
                }
            }
        } else {
            return null;
        }

        return $paths;
    }

    /**
     * Read one literal array of relations under a path prefix into the paths, as Laravel does: a string key over an
     * array nests, a string under an int key is a name, and a string key over anything else constrains that name.
     *
     * @param  RelationPaths  $paths
     */
    private function prepare(Array_ $array, string $prefix, array &$paths): bool
    {
        foreach ($array->items as $item) {
            $key = $item->key;

            if ($item->unpack || $item->byRef || ($key !== null && ! $key instanceof String_ && ! $key instanceof Int_)) {
                return false;
            }

            if (! $key instanceof String_) {
                if (! $item->value instanceof String_ || ! $this->addNamed($item->value->value, $prefix, $paths)) {
                    return false;
                }

                continue;
            }

            if ($item->value instanceof Array_) {
                [$name, $columns] = $this->nameAndColumns($key->value);

                if (! $this->isRelationPath($name)) {
                    return false;
                }

                $paths["{$prefix}{$name}"][] = $columns;

                if (! $this->prepare($item->value, "{$prefix}{$name}.", $paths)) {
                    return false;
                }

                continue;
            }

            // Laravel reads such a key whole, so a column list in it names no relation and the load throws.
            if (! $this->isRelationPath($key->value)) {
                return false;
            }

            // A constraint other than a closure, such as a variable, may nest relations or narrow columns unseen.
            $paths["{$prefix}{$key->value}"][] = $item->value instanceof ClosureExpr || $item->value instanceof ArrowFunction
                ? $item->value
                : false;
        }

        return true;
    }

    /**
     * Add a relation named as a string, `author.posts:id,title` included, to the paths under a prefix.
     *
     * @param  RelationPaths  $paths
     */
    private function addNamed(string $named, string $prefix, array &$paths): bool
    {
        [$name, $columns] = $this->nameAndColumns($named);

        if (! $this->isRelationPath($name)) {
            return false;
        }

        $paths["{$prefix}{$name}"][] = $columns;

        return true;
    }

    /**
     * A relation name and the columns its `:col,col` suffix selects, null where it selects none.
     *
     * @return array{string, list<string>|null}
     */
    private function nameAndColumns(string $named): array
    {
        $parts = explode(':', $named);

        return [$parts[0], isset($parts[1]) ? explode(',', $parts[1]) : null];
    }

    /**
     * Whether a dotted path names a method at each step, as Laravel calls each one to build the relation.
     */
    private function isRelationPath(string $path): bool
    {
        return preg_match('/^[A-Za-z_]\w*(\.[A-Za-z_]\w*)*$/', $path) === 1;
    }

    /**
     * The paths as a tree of relation names, each with its constraints and the relations loaded under it; a parent a
     * dotted path implies loads with none, as Builder::addNestedWiths() adds it.
     *
     * @param  RelationPaths  $paths
     * @return RelationTree
     */
    private function tree(array $paths): array
    {
        $tree = [];

        foreach ($paths as $path => $constraints) {
            $node = &$tree;
            $segments = explode('.', $path);
            $last = array_pop($segments);

            foreach ($segments as $segment) {
                $node[$segment] ??= ['constraints' => [], 'children' => []];
                $node = &$node[$segment]['children'];
            }

            $node[$last] ??= ['constraints' => [], 'children' => []];
            $node[$last]['constraints'] = $constraints;
            unset($node);
        }

        /** @var RelationTree $tree */
        return $tree;
    }

    /**
     * The members a model's loaded relations add, in the order the call names them; null where none can be typed or
     * the model writes them in a serializer of its own.
     *
     * @param  class-string<Model>  $model
     * @param  RelationTree  $nodes
     * @param  list<string>|null  $kept  for a loadMissing(), the paths under the model `$with` may have narrowed
     * @return LoadedMembers|null
     */
    private function membersOf(string $model, array $nodes, bool $unsaved, ?array $kept): ?array
    {
        $resolver = resolve(ModelAttributeResolver::class);
        $instance = $resolver->getInstance($model);

        if ($instance === null || ! $this->serializesRelations($model)) {
            return null;
        }

        $kept = $kept === null ? null : [...$kept, ...$this->narrowedByWith($instance)];

        $parts = [];
        $models = [];

        foreach ($nodes as $name => $node) {
            $key = $resolver->sentRelationKey($instance, $name);
            $member = $key === null || in_array($name, $kept ?? [], true) ? null : $this->member($instance, $name, $node, $unsaved, $kept);

            if ($member === null) {
                continue;
            }

            $parts[] = "{$key}: {$member['type']}";
            array_push($models, ...$member['models']);
        }

        return $parts === [] ? null : ['type' => '{ '.implode('; ', $parts).' }', 'models' => $models];
    }

    /**
     * One loaded relation's value as Laravel sends it; null where it is never sent or nothing here admits it.
     *
     * @param  array{constraints: list<Constraint>, children: array<string, mixed>}  $node
     * @param  list<string>|null  $kept
     * @return LoadedMembers|null
     */
    private function member(Model $instance, string $name, array $node, bool $unsaved, ?array $kept): ?array
    {
        $resolver = resolve(ModelAttributeResolver::class);
        $relation = $resolver->resolveRelation($instance::class, $name);
        $constraint = $this->constraintOf($node['constraints']);

        if ($relation['type'] === 'unknown' || $constraint === false) {
            return null;
        }

        /** @var RelationTree $children */
        $children = $node['children'];

        // A morphTo loads its targets' relations and columns per target, which one shape here cannot follow.
        if ($relation['morphFqcns'] !== []) {
            $type = $unsaved ? ValueResult::withNullArm($relation['type']) : $relation['type'];

            return $constraint === null && $children === [] ? ['type' => $type, 'models' => $relation['morphFqcns']] : null;
        }

        $related = $relation['modelFqcn'];

        if ($related === null) {
            return null;
        }

        $toMany = str_ends_with($relation['type'], '[]');
        $nullable = ValueResult::hasNullArm($relation['type']) || (! $toMany && $unsaved);
        $element = class_basename($related);

        if (is_array($constraint)) {
            $every = $constraint === ['*'];
            $picked = $every ? $element : $this->pickedColumns($related, $constraint);

            if ($picked === null) {
                return null;
            }

            $element = $picked;
            $nullable = $nullable || (! $toMany && ! $every && ! $this->matchesWith($instance, $name, $constraint));
        } elseif ($constraint !== null) {
            $nullable = $nullable || (! $toMany && ! $this->keepsRows($constraint));
        }

        $models = [$related];
        $under = $kept === null ? null : array_values(array_filter(array_map(
            fn (string $path): ?string => str_starts_with($path, "{$name}.") ? substr($path, strlen($name) + 1) : null,
            $kept,
        )));
        $nested = $children === [] ? null : $this->membersOf($related, $children, false, $under);

        if ($nested !== null) {
            $element = "{$element} & {$nested['type']}";
            array_push($models, ...$nested['models']);
        }

        $type = match (true) {
            $toMany => TsTypeShape::listType([$element]),
            $nullable => ValueResult::withNullArm($element),
            default => $element,
        };

        return ['type' => $type, 'models' => $models];
    }

    /**
     * What constrains one relation: nothing, its column list, or its closure; false where the call writes several
     * constraints for it or one this cannot read, such as a variable.
     *
     * @param  list<Constraint>  $constraints
     * @return list<string>|ClosureExpr|ArrowFunction|false|null
     */
    private function constraintOf(array $constraints): array|ClosureExpr|ArrowFunction|false|null
    {
        $written = array_values(array_filter($constraints, fn (mixed $constraint): bool => $constraint !== null));

        return match (true) {
            $written === [] => null,
            count($written) > 1, $written[0] === false => false,
            $written[0] instanceof ClosureExpr || $written[0] instanceof ArrowFunction => $this->readsClosure($written[0]) ? $written[0] : false,
            default => $written[0],
        };
    }

    /**
     * `Pick<Related, …>` over the columns a list selects, where each is a column the related model publishes.
     *
     * @param  class-string<Model>  $related
     * @param  list<string>  $columns
     */
    private function pickedColumns(string $related, array $columns): ?string
    {
        foreach ($columns as $column) {
            if (preg_match('/^\w+$/', $column) !== 1) {
                return null;
            }
        }

        return $this->relationFilterModelReference($related, array_values(array_unique($columns)), true);
    }

    /**
     * Whether a to-one relation still matches its row with only the given columns selected: the key Laravel matches
     * the row by is among them, else the relation loads as null.
     *
     * @param  list<string>  $columns
     */
    private function matchesWith(Model $instance, string $name, array $columns): bool
    {
        try {
            $relation = $instance->{$name}();
        } catch (Throwable) {
            return false;
        }

        $key = match (true) {
            $relation instanceof BelongsTo => $relation->getOwnerKeyName(),
            $relation instanceof HasOneOrMany => $relation->getForeignKeyName(),
            default => null,
        };

        return $key !== null && in_array($key, $columns, true);
    }

    /**
     * Whether a closure constraint is a chain of calls on its builder alone, each of them one this reads.
     */
    private function readsClosure(ClosureExpr|ArrowFunction $closure): bool
    {
        return $this->closureCalls($closure) !== null;
    }

    /**
     * Whether every call a closure constraint makes keeps each related row it would load.
     */
    private function keepsRows(ClosureExpr|ArrowFunction $closure): bool
    {
        return array_all($this->closureCalls($closure) ?? [], fn (string $call): bool => in_array($call, self::KEEPS_ROWS, true));
    }

    /**
     * The calls a closure constraint chains on its builder parameter, as its statements or arrow body; null where it
     * does anything else, or calls a method that may change the columns, such as `select()` or a scope.
     *
     * @return list<string>|null
     */
    private function closureCalls(ClosureExpr|ArrowFunction $closure): ?array
    {
        $builder = $closure->params[0]->var ?? null;

        if (! $builder instanceof Variable || ! is_string($builder->name)) {
            return null;
        }

        $exprs = [];

        foreach ($closure instanceof ArrowFunction ? [new Return_($closure->expr)] : $closure->stmts as $stmt) {
            if ($stmt instanceof Expression || ($stmt instanceof Return_ && $stmt->expr !== null)) {
                $exprs[] = $stmt->expr;
            } elseif (! $stmt instanceof Return_) {
                return null;
            }
        }

        $calls = [];

        foreach ($exprs as $expr) {
            while ($expr instanceof MethodCall && $expr->name instanceof Identifier) {
                $calls[] = $expr->name->toString();
                $expr = $expr->var;
            }

            if (! $expr instanceof Variable || $expr->name !== $builder->name) {
                return null;
            }
        }

        foreach ($calls as $call) {
            if (! in_array($call, self::KEEPS_ROWS, true) && ! in_array($call, self::FILTERS_ROWS, true) && ! $this->filtersByWhere($call)) {
                return null;
            }
        }

        return $calls;
    }

    /**
     * Whether a call is one of the builder's `where…()` or `orWhere…()` filters.
     */
    private function filtersByWhere(string $call): bool
    {
        return preg_match('/^(or)?[wW]here([A-Z]|$)/', $call) === 1;
    }

    /**
     * The relation paths a model's `$with` loads with columns or a constraint, which a loadMissing() then keeps as they
     * are; a nested list or a constraint counts its whole path.
     *
     * @return list<string>
     */
    private function narrowedByWith(Model $instance): array
    {
        $narrowed = [];

        foreach ((array) new ReflectionProperty($instance, 'with')->getValue($instance) as $key => $value) {
            if (is_string($key)) {
                $narrowed[] = explode(':', $key)[0];
            } elseif (is_string($value) && str_contains($value, ':')) {
                $narrowed[] = explode(':', $value)[0];
            }
        }

        return $narrowed;
    }

    /**
     * Whether a model writes its loaded relations by Model's own serializers.
     *
     * @param  class-string<Model>  $model
     */
    private function serializesRelations(string $model): bool
    {
        return array_all(
            self::SERIALIZERS,
            fn (string $method): bool => new ReflectionMethod($model, $method)->getDeclaringClass()->getName() === Model::class,
        );
    }
}
