<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\AnalyzesPluckCalls;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesRelatedModelTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\CallChainWalker;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\DeclaredClasses;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\QueryForwarding;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Support\TolkiTypes;
use Illuminate\Contracts\Pagination\CursorPaginator as CursorPaginatorContract;
use Illuminate\Contracts\Pagination\Paginator as PaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Enumerable;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use ReflectionMethod;

/**
 * Eloquent query chains rooted at a model, typed from the terminal call on the query each step before it hands on.
 *
 * `Post::findOrFail($id)`, `Post::query()->latest()->get()`, `Team::query()->paginate(10)`, a few collection steps on
 * a list such a terminal returns, a model factory's `make()` or `create()`, and a query sent as it is.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @phpstan-type ChainRead array{result: ValueExpressionResult, element: ValueExpressionResult|null, maybeOne?: bool}
 *
 * @internal
 */
final class ModelFinderHandler implements ExpressionHandler
{
    use AnalyzesPluckCalls;
    use ResolvesRelatedModelTypes;

    /** Terminals returning one model instance; the first three can miss and so are nullable. */
    private const SINGLE = [
        'find', 'first', 'firstWhere',
        'sole', 'firstOrFail', 'findOrFail', 'firstOrCreate', 'firstOrNew',
        'create', 'make', 'updateOrCreate',
    ];

    private const NULLABLE_SINGLE = ['find', 'first', 'firstWhere'];

    private const MANY = ['all', 'get', 'cursor', 'findMany'];

    /** Terminal => the paginator FQCN whose `@tolki/types` name wraps the model. */
    private const PAGINATORS = [
        'paginate' => 'Illuminate\\Pagination\\LengthAwarePaginator',
        'simplePaginate' => 'Illuminate\\Pagination\\Paginator',
        'cursorPaginate' => 'Illuminate\\Pagination\\CursorPaginator',
    ];

    private const SCALARS = ['count' => 'number', 'exists' => 'boolean'];

    /** Terminals reading one column of each row the query finds. */
    private const COLUMNS = ['pluck', 'value'];

    /** The factory calls that build models: a list of them once a count is set, else one. */
    private const FACTORY_BUILDS = ['make', 'create'];

    /** The factory calls that build one model whatever the count. */
    private const FACTORY_SINGLES = ['makeOne', 'createOne'];

    /** The factory calls that set the count; `count(null)` clears it. */
    private const FACTORY_COUNTS = ['count', 'times', 'forEachSequence'];

    /** What Inertia sends for a builder it is handed: json_encode() writes its one public property. */
    private const BUILDER_OBJECT = '{ pendingAttributes: Record<string, unknown> | never[] }';

    /** A row of a base query: the selected columns, which no model casts, hides or appends to. */
    private const ROW = 'Record<string, unknown>';

    /** Collection steps after a list => what they hand back: its element or null, its element, the list, or a count. */
    private const LIST_STEPS = [
        'first' => 'nullableElement', 'last' => 'nullableElement', 'firstWhere' => 'nullableElement',
        'sole' => 'element', 'firstOrFail' => 'element', 'find' => 'nullableElement',
        'get' => 'nullableElement', 'all' => 'list', 'values' => 'list', 'count' => 'count', 'pluck' => 'column',
    ];

    /** The list steps a model answers alike through a new query of itself, where the read may be one model. */
    private const ONE_OR_LIST_STEPS = ['first', 'firstWhere', 'sole', 'firstOrFail', 'count', 'pluck'];

    /** The classes Eloquent itself builds a chain on, whose own rules type a terminal on them. */
    private const ELOQUENT_CLASSES = [
        Model::class, Builder::class, Relation::class, QueryBuilder::class, Enumerable::class, Factory::class,
        PaginatorContract::class, CursorPaginatorContract::class,
    ];

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [StaticCall::class, MethodCall::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $call = $this->namedCall($expr);

        if ($call === null || resolve(CallChainWalker::class)->resolveRootClass($call, Model::class) === null) {
            return null;
        }

        return $this->isClaimed($this->nameOf($call), $call)
            ? $this->read($call, $scope)['result'] ?? null
            : $this->builderObject($call, $scope);
    }

    /**
     * A static or method call by a written name, other than a first-class callable; null for any other expression.
     */
    private function namedCall(Expr $expr): StaticCall|MethodCall|null
    {
        return ($expr instanceof StaticCall || $expr instanceof MethodCall) && $expr->name instanceof Identifier && ! $expr->isFirstClassCallable()
            ? $expr
            : null;
    }

    /**
     * The name a call namedCall() admitted is written with.
     */
    private function nameOf(StaticCall|MethodCall $call): string
    {
        return $call->name instanceof Identifier ? $call->name->toString() : '';
    }

    /**
     * Whether a call names a terminal, a factory build or a collection step this handler types.
     */
    private function isClaimed(string $name, StaticCall|MethodCall $call): bool
    {
        return $this->isTerminal($name)
            || in_array($name, self::FACTORY_SINGLES, true)
            || ($call instanceof MethodCall && isset(self::LIST_STEPS[$name]));
    }

    /**
     * A model's query sent as it is, `Post::query()->latest()` with no terminal: the builder object itself.
     *
     * @return ValueExpressionResult|null
     */
    private function builderObject(StaticCall|MethodCall $call, AnalysisScope $scope): ?array
    {
        $held = resolve(ReceiverClassResolver::class)->resolve($call, $scope);

        return $held !== null && $held->classes === [Builder::class] && $held->relatedModel !== null && ! $held->shortCircuits
            ? [...ValueResult::unknown(), 'type' => self::BUILDER_OBJECT]
            : null;
    }

    /**
     * What a call on a chain rooted at a model hands back, with a list's element; null past a step it cannot follow.
     *
     * @return ChainRead|null
     */
    private function read(Expr $expr, AnalysisScope $scope): ?array
    {
        $call = $this->namedCall($expr);

        if ($call === null) {
            return null;
        }

        $name = $this->nameOf($call);

        if ($call instanceof StaticCall) {
            $model = resolve(CallChainWalker::class)->resolveRootClass($call, Model::class);

            // Model::all() is the model's own static; any other runs on a new query only where the model lacks it.
            return $model === null || ! $this->isTerminal($name) || ($name !== 'all' && ! QueryForwarding::queriesStatically($model, $name))
                ? null
                : $this->typeFor($name, $model, $call, $scope);
        }

        return $this->factoryBuild($call, $name)
            ?? $this->queryTerminal($call, $name, $scope)
            ?? $this->listStep($call, $name, $scope)
            ?? $this->foreignBuilderTerminal($call, $name, $scope);
    }

    /**
     * A terminal on a query of one model, which each step before it handed on, or on a base query's rows.
     *
     * The query is an Eloquent builder or a relation, or a model, whose `__call()` runs a method it lacks on a new
     * query of itself.
     *
     * @return ChainRead|null
     */
    private function queryTerminal(MethodCall $call, string $terminal, AnalysisScope $scope): ?array
    {
        $receivers = resolve(ReceiverClassResolver::class);

        // A user-defined step no body proves or disproves keeps the reading every model-rooted chain had before.
        $receiver = $this->isTerminal($terminal)
            ? $receivers->resolve($call->var, $scope) ?? $receivers->resolvePresumingSteps($call->var, $scope)
            : null;

        if ($receiver === null) {
            return null;
        }

        $model = $this->queriedModel($receiver, $terminal);
        $read = match (true) {
            $model !== null => $this->typeFor($terminal, $model, $call, $scope),
            $receiver->within([QueryBuilder::class]) => $this->rowsFor($terminal),
            default => null,
        };

        if ($read === null || ! $receiver->shortCircuits) {
            return $read;
        }

        return ['result' => [...$read['result'], 'type' => ValueResult::withNullArm($read['result']['type'])], 'element' => null];
    }

    /**
     * A terminal on a base query, as `toBase()` hands one back, whose rows are plain objects.
     *
     * @return ChainRead|null
     */
    private function rowsFor(string $terminal): ?array
    {
        $row = [...ValueResult::unknown(), 'type' => self::ROW];

        return match (true) {
            isset(self::SCALARS[$terminal]) => ['result' => ['type' => self::SCALARS[$terminal], 'optional' => false], 'element' => null],
            isset(self::PAGINATORS[$terminal]) => ['result' => $this->paginatorOver($terminal, self::ROW), 'element' => null],
            in_array($terminal, ['get', 'cursor'], true) => ['result' => [...$row, 'type' => self::ROW.'[]'], 'element' => $row],
            in_array($terminal, self::NULLABLE_SINGLE, true) => ['result' => [...$row, 'type' => self::ROW.' | null'], 'element' => null],
            in_array($terminal, ['sole', 'firstOrFail'], true) => ['result' => $row, 'element' => null],
            default => null,
        };
    }

    /**
     * The model a receiver queries for a terminal: an Eloquent builder's or relation's, or a concrete model lacking it.
     *
     * @return class-string<Model>|null
     */
    private function queriedModel(ReceiverType $receiver, string $terminal): ?string
    {
        if ($receiver->relatedModel !== null && $receiver->within([Builder::class, Relation::class])) {
            return $receiver->relatedModel;
        }

        $model = $receiver->classes === [$receiver->classes[0]] ? $receiver->classes[0] : null;

        return $model !== null && ReceiverType::isConcreteModel($model) && ! method_exists($model, $terminal) ? $model : null;
    }

    /**
     * A terminal on a builder Eloquent does not define, as Scout's `search()` returns, typed against the root model.
     *
     * Base read every chain rooted at a model so, which matches what such a builder's terminals return.
     *
     * @return ChainRead|null
     */
    private function foreignBuilderTerminal(MethodCall $call, string $terminal, AnalysisScope $scope): ?array
    {
        $root = $this->namedCall(resolve(CallChainWalker::class)->chainRoot($call));
        $model = $this->isTerminal($terminal) && $root instanceof StaticCall
            ? resolve(CallChainWalker::class)->resolveRootClass($root, Model::class)
            : null;

        if ($root === null || $model === null || QueryForwarding::queriesStatically($model, $this->nameOf($root))) {
            return null;
        }

        $built = resolve(ReceiverClassResolver::class)->resolve($root, $scope);
        $foreign = $built !== null && array_all(
            $built->classes,
            fn (string $class): bool => ! array_any(self::ELOQUENT_CLASSES, fn (string $eloquent): bool => is_a($class, $eloquent, true)),
        );

        return $foreign ? $this->typeFor($terminal, $model, $call, $scope) : null;
    }

    /**
     * A model factory chain's `make()` or `create()`: one model, a list of it once a count is set, or either.
     *
     * A count step or a `factory()` argument sets the count, `makeOne()` and `createOne()` build one model, and a count
     * no literal spells may be either.
     *
     * @return ChainRead|null
     */
    private function factoryBuild(MethodCall $call, string $terminal): ?array
    {
        $single = in_array($terminal, self::FACTORY_SINGLES, true);

        if (! $single && ! in_array($terminal, self::FACTORY_BUILDS, true)) {
            return null;
        }

        $steps = [];
        $node = $call->var;

        while ($node instanceof MethodCall) {
            if (! $node->name instanceof Identifier || $node->isFirstClassCallable() || ! $this->isFactoryStep($node->name->toString())) {
                return null;
            }

            $steps[] = $node;
            $node = $node->var;
        }

        $model = $this->factoryModel($node);

        if ($model === null || ! $node instanceof StaticCall) {
            return null;
        }

        $count = $this->countOf(CallArguments::for($node, new ReflectionMethod($model, 'factory'))->named('count')?->value, true);

        foreach (array_reverse($steps) as $step) {
            if ($step->name instanceof Identifier && in_array($step->name->toString(), self::FACTORY_COUNTS, true)) {
                $count = $step->name->toString() === 'forEachSequence' ? 'many' : $this->countOf($step->getArgs()[0]->value ?? null, false);
            }
        }

        $element = [...ValueResult::unknown(), 'type' => class_basename($model), 'modelFqcn' => $model];

        return match ($single ? 'one' : $count) {
            'one' => ['result' => $element, 'element' => null],
            'many' => ['result' => [...$element, 'type' => $element['type'].'[]'], 'element' => $element],
            default => ['result' => [...$element, 'type' => $element['type'].' | '.$element['type'].'[]'], 'element' => $element, 'maybeOne' => true],
        };
    }

    /**
     * Whether a count a factory is given builds one model, a list, or either.
     *
     * An int sets a count and `null` clears it; `factory()` reads an array or callable as state, and anything else
     * may hold either.
     *
     * @return 'one'|'many'|'either'
     */
    private function countOf(?Expr $count, bool $factoryArgument): string
    {
        return match (true) {
            $count instanceof Int_ => 'many',
            $count === null && $factoryArgument, $count instanceof ConstFetch && $count->name->toLowerString() === 'null' => 'one',
            $factoryArgument && ($count instanceof Array_ || $count instanceof ClosureExpr || $count instanceof ArrowFunction) => 'one',
            default => 'either',
        };
    }

    /**
     * Whether a call on a factory hands the factory on.
     *
     * One the base factory declares returning itself, or one it lacks, which the model's own factory class or
     * `Factory::__call()`'s `for…()` and `has…()` answer.
     */
    private function isFactoryStep(string $method): bool
    {
        return ! method_exists(Factory::class, $method) || DeclaredClasses::returnClasses(Factory::class, $method) === [Factory::class];
    }

    /**
     * The model a `M::factory()` root builds, or null for any other root.
     *
     * @return class-string<Model>|null
     */
    private function factoryModel(Expr $root): ?string
    {
        if (! $root instanceof StaticCall || ! $root->name instanceof Identifier || $root->name->toString() !== 'factory') {
            return null;
        }

        $model = resolve(CallChainWalker::class)->resolveRootClass($root, Model::class);

        return $model !== null && method_exists($model, 'factory') ? $model : null;
    }

    /**
     * A collection step on a list a terminal or factory returned: an element, the list, its count or a column.
     *
     * Where the read may be one model instead, only a step the model answers alike through a new query of itself reads.
     *
     * @return ChainRead|null
     */
    private function listStep(MethodCall $call, string $step, AnalysisScope $scope): ?array
    {
        $kind = self::LIST_STEPS[$step] ?? null;
        $inner = $kind === null ? null : $this->read($call->var, $scope);
        $element = $inner['element'] ?? null;

        if ($kind === null || $inner === null || $element === null || ! $this->takesStepArguments($call, $step, $element)) {
            return null;
        }

        if (($inner['maybeOne'] ?? false)
            && (! in_array($step, self::ONE_OR_LIST_STEPS, true) || method_exists($element['modelFqcn'] ?? Model::class, $step))
        ) {
            return null;
        }

        return match ($kind) {
            'nullableElement' => ['result' => [...$element, 'type' => ValueResult::withNullArm($element['type'])], 'element' => null],
            'element' => ['result' => $element, 'element' => null],
            'list' => $inner,
            'count' => ['result' => ['type' => 'number', 'optional' => false], 'element' => null],
            'column' => $this->pluckedColumn($call, $element, $scope),
        };
    }

    /**
     * Whether a collection step's arguments leave its answer as listed.
     *
     * A `first()` or `last()` with a callback at most, a `find()` on models of one literal key, a `get()` of one key
     * with no default, and a `pluck()` of models.
     *
     * @param  ValueExpressionResult  $element
     */
    private function takesStepArguments(MethodCall $call, string $step, array $element): bool
    {
        $args = $call->getArgs();
        $first = $args[0]->value ?? null;

        return match ($step) {
            'first', 'last' => $args === [] || (count($args) === 1 && ($first instanceof ClosureExpr || $first instanceof ArrowFunction)),
            'find' => isset($element['modelFqcn']) && count($args) === 1 && ($first instanceof Int_ || $first instanceof String_),
            'get' => count($args) === 1 && ! $args[0]->unpack,
            'pluck' => isset($element['modelFqcn']),
            'firstWhere' => true,
            default => $args === [],
        };
    }

    /**
     * Whether a terminal method name is one this handler knows the payload shape of.
     */
    private function isTerminal(string $terminal): bool
    {
        return in_array($terminal, self::SINGLE, true)
            || in_array($terminal, self::MANY, true)
            || in_array($terminal, self::COLUMNS, true)
            || isset(self::PAGINATORS[$terminal])
            || isset(self::SCALARS[$terminal]);
    }

    /**
     * Build the result for a known terminal against the model it queries.
     *
     * @param  class-string<Model>  $modelFqcn
     * @return ChainRead|null
     */
    private function typeFor(string $terminal, string $modelFqcn, StaticCall|MethodCall $call, AnalysisScope $scope): ?array
    {
        if (isset(self::SCALARS[$terminal])) {
            return ['result' => ['type' => self::SCALARS[$terminal], 'optional' => false], 'element' => null];
        }

        if (in_array($terminal, self::COLUMNS, true)) {
            return $this->queriedColumn($terminal, $modelFqcn, $call, $scope);
        }

        $basename = class_basename($modelFqcn);

        if (isset(self::PAGINATORS[$terminal])) {
            return ['result' => $this->paginatorOver($terminal, $basename, $modelFqcn), 'element' => null];
        }

        $model = [...ValueResult::unknown(), 'type' => $basename, 'modelFqcn' => $modelFqcn];
        $key = $this->findsKeys($terminal) ? $call->getArgs()[0]->value ?? null : null;

        // Laravel finds a collection for a list of keys, which the docblock spells as a conditional on the argument.
        if (in_array($terminal, self::MANY, true) || $key instanceof Array_) {
            return ['result' => [...$model, 'type' => $basename.'[]'], 'element' => $model];
        }

        $found = ['result' => [...$model, 'type' => $basename.(in_array($terminal, self::NULLABLE_SINGLE, true) ? ' | null' : '')], 'element' => null];

        // A key no literal spells may be a list, so only a step the model answers alike reads past it.
        return $key !== null && ! $key instanceof Int_ && ! $key instanceof String_ ? [...$found, 'element' => $model, 'maybeOne' => true] : $found;
    }

    /**
     * The `@tolki/types` paginator a paginating terminal returns, over a model or a base query's rows.
     *
     * @param  class-string<Model>|null  $modelFqcn
     * @return ValueExpressionResult
     */
    private function paginatorOver(string $terminal, string $element, ?string $modelFqcn = null): array
    {
        $tolkiName = TolkiTypes::MAP[self::PAGINATORS[$terminal]];
        $paginator = [...ValueResult::unknown(), 'type' => $tolkiName.'<'.$element.'>'];

        return [
            ...($modelFqcn === null ? $paginator : [...$paginator, 'modelFqcn' => $modelFqcn]),
            'customImports' => [TolkiTypes::MODULE => [$tolkiName]],
        ];
    }

    /**
     * Whether a terminal looks rows up by key, as `find()` and `findOrFail()` do.
     */
    private function findsKeys(string $terminal): bool
    {
        return $terminal === 'find' || $terminal === 'findOrFail';
    }

    /**
     * A query's `pluck('column')` or `value('column')`, typed by what the column reads as on the model.
     *
     * A pluck lists it, a value is the first row's or null, and a pluck keyed by a second column declines.
     *
     * @param  class-string<Model>  $modelFqcn
     * @return ChainRead|null
     */
    private function queriedColumn(string $terminal, string $modelFqcn, StaticCall|MethodCall $call, AnalysisScope $scope): ?array
    {
        $arguments = CallArguments::for($call, new ReflectionMethod(Builder::class, $terminal));
        $column = $arguments->named('column')?->value;

        if ($arguments->hasUnpack() || ! $column instanceof String_ || $arguments->named('key') !== null) {
            return null;
        }

        $value = $this->analyzeRelatedModelProperty($column->value, $scope, $modelFqcn);

        return $terminal === 'value'
            ? ['result' => [...$value, 'type' => ValueResult::withNullArm($value['type'])], 'element' => null]
            : ['result' => [...$value, 'type' => ValueResult::arrayWrapType($value['type'])], 'element' => $value];
    }

    /**
     * A `pluck('column')` on a list of models: what the column reads as on each, keyed by a second column declines.
     *
     * @param  ValueExpressionResult  $element
     * @return ChainRead|null
     */
    private function pluckedColumn(MethodCall $call, array $element, AnalysisScope $scope): ?array
    {
        $model = $element['modelFqcn'] ?? null;
        $arguments = CallArguments::for($call, new ReflectionMethod(EloquentCollection::class, 'pluck'));

        if ($model === null || ! is_a($model, Model::class, true) || $arguments->hasUnpack() || $arguments->named('key') !== null) {
            return null;
        }

        $list = $this->analyzeVariablePluckCall($call, $scope, $model);

        return ['result' => $list, 'element' => [...$list, 'type' => $this->listElement($list['type'])]];
    }

    /**
     * The element type of a list type `T[]` or `(A | B)[]`.
     */
    private function listElement(string $list): string
    {
        $element = substr($list, 0, -2);

        return str_starts_with($element, '(') && str_ends_with($element, ')') ? substr($element, 1, -1) : $element;
    }
}
