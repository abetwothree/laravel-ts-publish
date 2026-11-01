<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Receivers;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesFilteredRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
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
 * The relations a `load()` or `loadMissing()` call adds to what a model serializes, read off its literal argument.
 *
 * The argument is read as Eloquent\Builder::prepareNestedWithRelationships() reads it: names, lists, dotted paths,
 * nested arrays, closures and `:col,col` lists. Each member is typed as the relation's read publishes it, under the key
 * toArray() writes; one whose payload no type here admits is left out, so its key goes unclaimed.
 *
 * @phpstan-type Constraint = list<string>|ClosureExpr|ArrowFunction|false|null
 * @phpstan-type RelationPaths = array<string, list<Constraint>>
 * @phpstan-type RelationTree = array<string, array{constraints: list<Constraint>, children: array<string, mixed>}>
 * @phpstan-type LoadedMembers = array{type: string, models: list<class-string>}
 * @phpstan-type ChainRead = array{kept: list<string>, hidden: list<string>, shown: list<list<string>>}
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

    /** The calls, lowercased, that load the relations their argument names as `load()` reads it. */
    private const array EAGER_LOADS = ['with', 'withonly', 'load', 'loadmissing'];

    /**
     * The calls, lowercased, a chain reads through besides each `where…()`: Eloquent\Builder's and Query\Builder's own
     * steps that only filter, order or limit rows, the query, and the fetches that hand back the rows they find.
     */
    private const array READS_THROUGH = [
        'orderby', 'orderbydesc', 'orderbyraw', 'latest', 'oldest', 'reorder', 'reorderdesc', 'inrandomorder', 'limit',
        'take', 'skip', 'offset', 'forpage', 'has', 'orhas', 'doesnthave', 'ordoesnthave', 'withtrashed', 'onlytrashed',
        'withouttrashed', 'query', 'first', 'firstorfail', 'firstwhere', 'find', 'findorfail', 'sole', 'get', 'all',
    ];

    /** The classes a step is read through on: the model, its query or a relation, or a collection of models. */
    private const array QUERY_CLASSES = [Model::class, Builder::class, Relation::class, EloquentCollection::class];

    /** The calls, lowercased, that keep the names they are given out of toArray(). */
    private const array HIDES = ['makehidden', 'makehiddenif', 'mergehidden', 'sethidden'];

    /** The calls, lowercased, after which toArray() writes only the names they are given. */
    private const array SHOWS_ONLY = ['setvisible', 'mergevisible'];

    /** The Model methods that write a loaded relation into the array Inertia and json_encode() send. */
    private const array SERIALIZERS = ['toArray', 'jsonSerialize', 'relationsToArray', 'getArrayableRelations', 'getArrayableItems'];

    /**
     * The `{ key: Type; … }` object a `load()` or `loadMissing()` call adds to a model, and the models it names.
     *
     * Null where the argument is not a literal, Laravel throws on it, or it adds no member this types.
     *
     * @param  class-string<Model>  $model
     * @param  bool  $unsaved  no row may back the model yet, so it may hold no key a to-one relation is matched by
     * @return LoadedMembers|null
     */
    public function members(string $model, MethodCall|NullsafeMethodCall $call, AnalysisScope $scope, bool $unsaved = false): ?array
    {
        $paths = $this->relationPaths($call);
        $missing = $call->name instanceof Identifier && $call->name->toLowerString() === 'loadmissing';
        $chain = $this->chainBefore($call->var, $model, $missing, $scope);

        if ($paths === null || $chain === null) {
            return null;
        }

        $sent = array_filter(
            $this->tree($paths),
            fn (string $name): bool => ! in_array($name, $chain['hidden'], true)
                && array_all($chain['shown'], fn (array $names): bool => in_array($name, $names, true)),
            ARRAY_FILTER_USE_KEY,
        );

        return $this->membersOf($model, $sent, $unsaved ? [] : null, $missing ? $chain['kept'] : null);
    }

    /**
     * Each relation path a call names, with the constraints written for it.
     *
     * @return RelationPaths|null
     */
    private function relationPaths(MethodCall|NullsafeMethodCall|StaticCall $call): ?array
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
     * What the calls earlier in a load's chain did to the model, or null where one may have done anything.
     *
     * Only steps known to load, hide or leave relations alone are read: an eager load, a visibility call, a step
     * readsThrough() admits, and the signed-in user; `kept`, `hidden` and `shown` are read as members() says.
     *
     * @param  class-string<Model>  $model
     * @return ChainRead|null
     */
    private function chainBefore(Expr $receiver, string $model, bool $missing, AnalysisScope $scope): ?array
    {
        $read = ['kept' => [], 'hidden' => [], 'shown' => []];

        while ($receiver instanceof MethodCall || $receiver instanceof NullsafeMethodCall || $receiver instanceof StaticCall) {
            if (! $receiver->name instanceof Identifier || $receiver->isFirstClassCallable()) {
                return null;
            }

            $name = $receiver->name->toLowerString();

            // Whatever reached the signed-in user is another request's work, as a variable's value is.
            if ($name === 'user' && $this->holdsModels($receiver, $scope)) {
                break;
            }

            $visibility = in_array($name, self::HIDES, true) || in_array($name, self::SHOWS_ONLY, true);
            $eager = in_array($name, self::EAGER_LOADS, true);
            $names = $visibility ? $this->literalNames($receiver, $name === 'makehiddenif' ? 1 : 0) : [];
            $paths = $eager && $missing ? $this->relationPaths($receiver) : [];

            if ($names === null || $paths === null || (! $visibility && ! $eager && ! $this->readsThrough($receiver, $model, $missing, $scope))) {
                return null;
            }

            if (in_array($name, self::HIDES, true)) {
                array_push($read['hidden'], ...$names);
            } elseif ($visibility) {
                $read['shown'][] = $names;
            }

            foreach ($paths as $path => $constraints) {
                if (array_any($constraints, fn (mixed $constraint): bool => $constraint !== null)) {
                    $read['kept'][] = $path;
                }
            }

            $receiver = $receiver instanceof StaticCall ? null : $receiver->var;
        }

        return $read;
    }

    /**
     * Whether a chain reads through a step: one READS_THROUGH lists, a `where…()` or a bare `fresh()`, on the model,
     * its query or its collection, that no code of the application's own answers.
     *
     * A scope runs on the query, which can hide nothing, so a load() reads through it; it may eager load, which a
     * loadMissing() would then keep, so a loadMissing() does not.
     *
     * @param  class-string<Model>  $model
     */
    private function readsThrough(MethodCall|NullsafeMethodCall|StaticCall $step, string $model, bool $missing, AnalysisScope $scope): bool
    {
        $call = $step->name instanceof Identifier ? $step->name->toString() : '';
        $name = strtolower($call);
        $listed = match (true) {
            ! $missing && QueryForwarding::namedScope($model, $call) !== null => true,
            $name === 'fresh' => $step->getArgs() === [],
            default => (in_array($name, self::READS_THROUGH, true) || $this->filtersByWhere($call)) && ! $this->runsUserCode($model, $call),
        };
        $receivers = resolve(ReceiverClassResolver::class);
        $on = $step instanceof StaticCall ? $receivers->resolveStaticReceiver($step, $scope) : $receivers->resolve($step->var, $scope);

        return $listed && $on !== null && $on->within(self::QUERY_CLASSES);
    }

    /**
     * Whether a call holds only models, as `auth()->user()` and `$request->user()` hand back the signed-in user.
     */
    private function holdsModels(MethodCall|NullsafeMethodCall|StaticCall $call, AnalysisScope $scope): bool
    {
        $held = resolve(ReceiverClassResolver::class)->resolve($call, $scope);

        return $held !== null && $held->models() === $held->classes;
    }

    /**
     * The names a visibility call is given as literal strings, from the argument at a position on; null for any other.
     *
     * @return list<string>|null
     */
    private function literalNames(MethodCall|NullsafeMethodCall|StaticCall $call, int $from): ?array
    {
        if ($call->isFirstClassCallable()) {
            return null;
        }

        $args = array_slice($call->getArgs(), $from);
        $values = $args !== [] && $args[0]->value instanceof Array_
            ? array_map(fn (ArrayItem $item): ?Expr => $item->key === null && ! $item->unpack ? $item->value : null, $args[0]->value->items)
            : array_map(fn (Arg $arg): ?Expr => $arg->unpack || $arg->name !== null ? null : $arg->value, $args);
        $names = [];

        foreach ($values as $value) {
            if (! $value instanceof String_) {
                return null;
            }

            $names[] = $value->value;
        }

        return $names === [] ? null : $names;
    }

    /**
     * Read one literal array of relations under a path prefix into the paths, as Laravel reads it.
     *
     * A string key over an array nests, a string under an int key is a name, and a string key over anything else
     * constrains that name.
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
     * The paths as a tree of relation names, each with its constraints and the relations loaded under it.
     *
     * A parent a dotted path implies loads with no constraint, as Builder::addNestedWiths() adds it.
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
     * The members a model's loaded relations add, in the order the call names them.
     *
     * Null where none can be typed, or the model writes them in a serializer of its own.
     *
     * @param  class-string<Model>  $model
     * @param  RelationTree  $nodes
     * @param  list<string>|null  $held  the columns the model holds, as a column list selected them; null for every one
     * @param  list<string>|null  $kept  for a loadMissing(), the paths under the model `$with` may have narrowed
     * @return LoadedMembers|null
     */
    private function membersOf(string $model, array $nodes, ?array $held, ?array $kept): ?array
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
            $member = $key === null || in_array($name, $kept ?? [], true) ? null : $this->member($instance, $name, $node, $held, $kept);

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
     * @param  list<string>|null  $held
     * @param  list<string>|null  $kept
     * @return LoadedMembers|null
     */
    private function member(Model $instance, string $name, array $node, ?array $held, ?array $kept): ?array
    {
        $relation = resolve(ModelAttributeResolver::class)->resolveRelation($instance::class, $name);
        $constraint = $this->constraintOf($node['constraints']);

        if ($relation['type'] === 'unknown' || $constraint === false) {
            return null;
        }

        /** @var RelationTree $children */
        $children = $node['children'];
        $toMany = str_ends_with($relation['type'], '[]');

        // A morphTo loads its targets' relations and columns per target, which one shape here cannot follow.
        if ($relation['morphFqcns'] !== []) {
            $type = $this->matches($instance, $name, $held, null) ? $relation['type'] : ValueResult::withNullArm($relation['type']);

            return $constraint === null && $children === [] ? ['type' => $type, 'models' => $relation['morphFqcns']] : null;
        }

        $related = $relation['modelFqcn'];

        if ($related === null) {
            return null;
        }

        $selected = is_array($constraint) && $constraint !== ['*'] ? $constraint : null;
        $calls = $constraint instanceof ClosureExpr || $constraint instanceof ArrowFunction ? $this->closureCalls($constraint, $related) : [];
        $element = $selected === null ? class_basename($related) : $this->pickedColumns($related, $selected);

        if ($calls === null || $element === null) {
            return null;
        }

        $nullable = ValueResult::hasNullArm($relation['type'])
            || (! $toMany && (! $this->matches($instance, $name, $held, $selected) || ! $this->keepsRows($calls)));

        $models = [$related];
        $under = $kept === null ? null : array_values(array_filter(array_map(
            fn (string $path): ?string => str_starts_with($path, "{$name}.") ? substr($path, strlen($name) + 1) : null,
            $kept,
        )));
        $nested = $children === [] ? null : $this->membersOf($related, $children, $selected, $under);

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
     * What constrains one relation: nothing, its column list, its closure, or false where this cannot read it.
     *
     * Several constraints written for one relation, or one other than a closure, such as a variable, count as unread.
     *
     * @param  list<Constraint>  $constraints
     * @return list<string>|ClosureExpr|ArrowFunction|false|null
     */
    private function constraintOf(array $constraints): array|ClosureExpr|ArrowFunction|false|null
    {
        $written = array_values(array_filter($constraints, fn (mixed $constraint): bool => $constraint !== null));

        return match (true) {
            $written === [] => null,
            count($written) > 1 => false,
            default => $written[0],
        };
    }

    /**
     * `Pick<Related, …>` over the columns a list selects that toArray() writes, each one a column the model publishes.
     *
     * A column `$hidden` lists, or `$visible` leaves out, is loaded but never sent, so the Pick leaves it out too.
     *
     * @param  class-string<Model>  $related
     * @param  list<string>  $columns
     */
    private function pickedColumns(string $related, array $columns): ?string
    {
        $resolver = resolve(ModelAttributeResolver::class);
        $instance = $resolver->getInstance($related);

        if ($instance === null || array_any($columns, fn (string $column): bool => preg_match('/^\w+$/', $column) !== 1)) {
            return null;
        }

        $sent = array_values(array_filter(array_unique($columns), fn (string $column): bool => $resolver->writesName($instance, $column)));

        return $this->relationFilterModelReference($related, $sent, true);
    }

    /**
     * Whether a to-one relation still finds its row, or loads as null.
     *
     * Laravel matches the row by a key on each side: the parent's must be among the columns it holds, and the related
     * model's among those a column list selects.
     *
     * @param  list<string>|null  $held
     * @param  list<string>|null  $selected
     */
    private function matches(Model $instance, string $name, ?array $held, ?array $selected): bool
    {
        if ($held === null && $selected === null) {
            return true;
        }

        try {
            $relation = $instance->{$name}();
        } catch (Throwable) {
            return false;
        }

        [$parentKeys, $relatedKey] = match (true) {
            $relation instanceof MorphTo => [[$relation->getForeignKeyName(), $relation->getMorphType()], null],
            $relation instanceof BelongsTo => [[$relation->getForeignKeyName()], $relation->getOwnerKeyName()],
            $relation instanceof HasOneOrMany => [[$relation->getLocalKeyName()], $relation->getForeignKeyName()],
            default => [null, null],
        };

        return $parentKeys !== null
            && ($held === null || array_diff($parentKeys, $held) === [])
            && ($selected === null || ($relatedKey !== null && in_array($relatedKey, $selected, true)));
    }

    /**
     * Whether every call a closure constraint makes keeps each related row it would load.
     *
     * @param  list<string>  $calls
     */
    private function keepsRows(array $calls): bool
    {
        return array_all($calls, fn (string $call): bool => in_array($call, self::KEEPS_ROWS, true));
    }

    /**
     * The calls a closure constraint chains on its builder parameter, null where it may do anything else.
     *
     * Its statements or arrow body must each be a chain of such calls, each one that keeps or filters rows; a call the
     * related model's scope or a builder macro answers instead, such as a `scopeWhereSlim()`, may change the columns.
     *
     * @param  class-string<Model>  $related
     * @return list<string>|null
     */
    private function closureCalls(ClosureExpr|ArrowFunction $closure, string $related): ?array
    {
        $builder = $closure->params[0]->var ?? null;
        $instance = resolve(ModelAttributeResolver::class)->getInstance($related);

        if (! $builder instanceof Variable || ! is_string($builder->name) || $instance === null) {
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
            $read = in_array($call, self::KEEPS_ROWS, true) || in_array($call, self::FILTERS_ROWS, true) || $this->filtersByWhere($call);

            if (! $read || $this->runsUserCode($related, $call)) {
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
     * Whether a call on a model or its query runs the application's own code: a declared method, a macro or a scope.
     *
     * Builder::__call() runs a builder macro or the model's scope for a name the Eloquent builder does not declare.
     *
     * @param  class-string<Model>  $model
     */
    private function runsUserCode(string $model, string $call): bool
    {
        return $this->declaresOwn($model, $call)
            || $this->declaresOwn(QueryForwarding::builderClass($model), $call)
            || (! method_exists(Builder::class, $call) && (Builder::hasGlobalMacro($call) || QueryForwarding::namedScope($model, $call) !== null));
    }

    /**
     * Whether a class declares a method of this name in the application's code rather than Laravel's.
     *
     * @param  class-string  $class
     */
    private function declaresOwn(string $class, string $method): bool
    {
        return method_exists($class, $method)
            && ! str_starts_with(new ReflectionMethod($class, $method)->getDeclaringClass()->getName(), 'Illuminate\\');
    }

    /**
     * The relation paths a model's `$with` loads with columns or a constraint, which a loadMissing() keeps as they are.
     *
     * A nested list or a constraint counts its whole path.
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
