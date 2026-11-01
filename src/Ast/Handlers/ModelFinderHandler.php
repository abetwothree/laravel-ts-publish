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
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Support\TolkiTypes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use ReflectionMethod;

/**
 * Eloquent query chains rooted at a model — `Post::findOrFail($id)`, `Post::query()->latest()->get()`,
 * `Team::query()->paginate(10)` — typed from the terminal call on the query each step before it hands on, a few
 * collection steps on a list it returns, a model factory's `make()` or `create()`, and a query sent as it is.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @phpstan-type ChainRead array{result: ValueExpressionResult, element: ValueExpressionResult|null}
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

    /** The factory calls that set the count; `count(null)` clears it. */
    private const FACTORY_COUNTS = ['count', 'times', 'forEachSequence'];

    /** What Inertia sends for a builder it is handed: json_encode() writes its one public property. */
    private const BUILDER_OBJECT = '{ pendingAttributes: Record<string, unknown> | never[] }';

    /** Collection steps after a list => what they hand back: its element or null, its element, the list, or a count. */
    private const LIST_STEPS = [
        'first' => 'nullableElement', 'last' => 'nullableElement', 'firstWhere' => 'nullableElement',
        'sole' => 'element', 'firstOrFail' => 'element', 'find' => 'nullableElement',
        'get' => 'nullableElement', 'all' => 'list', 'values' => 'list', 'count' => 'count', 'pluck' => 'column',
    ];

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [StaticCall::class, MethodCall::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if ((! $expr instanceof StaticCall && ! $expr instanceof MethodCall) || ! $expr->name instanceof Identifier || $expr->isFirstClassCallable()) {
            return null;
        }

        if (resolve(CallChainWalker::class)->resolveRootClass($expr, Model::class) === null) {
            return null;
        }

        $read = $this->isClaimed($expr->name->toString(), $expr) ? $this->read($expr, $scope) : null;

        return $read['result'] ?? $this->builderObject($expr, $scope);
    }

    /**
     * Whether a call names a terminal, a factory build or a collection step this handler types.
     */
    private function isClaimed(string $name, StaticCall|MethodCall $call): bool
    {
        return $this->isTerminal($name)
            || in_array($name, ['makeOne', 'createOne'], true)
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
     * What a call on a chain rooted at a model hands back, with the element of a list; null where a step is not one
     * this handler can follow.
     *
     * @return ChainRead|null
     */
    private function read(Expr $expr, AnalysisScope $scope): ?array
    {
        if ((! $expr instanceof StaticCall && ! $expr instanceof MethodCall) || ! $expr->name instanceof Identifier || $expr->isFirstClassCallable()) {
            return null;
        }

        $name = $expr->name->toString();

        if ($expr instanceof StaticCall) {
            $model = $expr->class instanceof Name ? resolve(CallChainWalker::class)->resolveRootClass($expr, Model::class) : null;

            return $model === null || ! $this->isTerminal($name) ? null : $this->typeFor($name, $model, $expr, $scope);
        }

        return $this->factoryBuild($expr, $name)
            ?? $this->queryTerminal($expr, $name, $scope)
            ?? $this->listStep($expr, $name, $scope);
    }

    /**
     * A terminal on a query of one model, which each step before it handed on: an Eloquent builder or a relation, or
     * a model, whose `__call()` runs a method it lacks on a new query of itself.
     *
     * @return ChainRead|null
     */
    private function queryTerminal(MethodCall $call, string $terminal, AnalysisScope $scope): ?array
    {
        if (! $this->isTerminal($terminal)) {
            return null;
        }

        $receiver = resolve(ReceiverClassResolver::class)->resolve($call->var, $scope);
        $model = $receiver === null ? null : $this->queriedModel($receiver, $terminal);

        if ($receiver === null) {
            return null;
        }

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
     * A terminal on a base query, as `toBase()` hands one back: rows are plain objects of the selected columns, which
     * no model casts, hides or appends to.
     *
     * @return ChainRead|null
     */
    private function rowsFor(string $terminal): ?array
    {
        $row = [...ValueResult::unknown(), 'type' => 'Record<string, unknown>'];

        return match (true) {
            isset(self::SCALARS[$terminal]) => ['result' => ['type' => self::SCALARS[$terminal], 'optional' => false], 'element' => null],
            in_array($terminal, ['get', 'cursor'], true) => ['result' => [...$row, 'type' => 'Record<string, unknown>[]'], 'element' => $row],
            in_array($terminal, self::NULLABLE_SINGLE, true) => ['result' => [...$row, 'type' => 'Record<string, unknown> | null'], 'element' => null],
            in_array($terminal, ['sole', 'firstOrFail'], true) => ['result' => $row, 'element' => null],
            default => null,
        };
    }

    /**
     * The model a receiver queries for a terminal: the related model of an Eloquent builder or a relation, or a
     * concrete model that lacks the terminal; null for any other receiver.
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
     * A model factory chain's `make()` or `create()`, `M::factory()->…->make()`: a list of the model once a count step
     * or a numeric `factory()` argument sets a count, else one model, as `makeOne()` and `createOne()` always build.
     *
     * @return ChainRead|null
     */
    private function factoryBuild(MethodCall $call, string $terminal): ?array
    {
        $single = in_array($terminal, ['makeOne', 'createOne'], true);

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

        $counted = $this->factoryCount($node, $model);

        foreach (array_reverse($steps) as $step) {
            if ($step->name instanceof Identifier && in_array($step->name->toString(), self::FACTORY_COUNTS, true)) {
                $counted = ! $this->clearsCount($step);
            }
        }

        $element = [...ValueResult::unknown(), 'type' => class_basename($model), 'modelFqcn' => $model];

        return $counted && ! $single
            ? ['result' => [...$element, 'type' => $element['type'].'[]'], 'element' => $element]
            : ['result' => $element, 'element' => null];
    }

    /**
     * Whether a call on a factory hands the factory on: one the base factory declares returning itself, or one it
     * lacks, which the model's own factory class or `Factory::__call()`'s `for…()` and `has…()` answer.
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
     * Whether `M::factory()`'s first argument sets a count: `HasFactory::factory()` reads a number as one.
     *
     * @param  class-string<Model>  $model
     */
    private function factoryCount(StaticCall $root, string $model): bool
    {
        return CallArguments::for($root, new ReflectionMethod($model, 'factory'))->named('count')?->value instanceof Int_;
    }

    /**
     * Whether a count step passes `null`, which clears the count so the factory builds one model.
     */
    private function clearsCount(MethodCall $step): bool
    {
        $count = $step->getArgs()[0]->value ?? null;

        return $count instanceof ConstFetch && $count->name->toLowerString() === 'null';
    }

    /**
     * A collection step on a list a terminal or factory returned: its element, null where none matches, the list
     * itself, its count, or a column of each model in it.
     *
     * @return ChainRead|null
     */
    private function listStep(MethodCall $call, string $step, AnalysisScope $scope): ?array
    {
        $kind = self::LIST_STEPS[$step] ?? null;
        $inner = $kind === null ? null : $this->read($call->var, $scope);
        $element = $inner['element'] ?? null;

        if ($inner === null || $element === null || ! $this->takesStepArguments($call, $step, $element)) {
            return null;
        }

        return match ($kind) {
            'nullableElement' => ['result' => [...$element, 'type' => ValueResult::withNullArm($element['type'])], 'element' => null],
            'element' => ['result' => $element, 'element' => null],
            'list' => $inner,
            'count' => ['result' => ['type' => 'number', 'optional' => false], 'element' => null],
            default => $this->pluckedColumn($call, $element, $scope),
        };
    }

    /**
     * Whether a collection step's arguments leave its answer as listed: a `first()` or `last()` with a callback at
     * most, a `find()` on models of one literal key, a `get()` of one key with no default, and a `pluck()` of models.
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
            $tolkiName = TolkiTypes::MAP[self::PAGINATORS[$terminal]];

            return ['result' => [
                ...ValueResult::unknown(),
                'type' => $tolkiName.'<'.$basename.'>',
                'modelFqcn' => $modelFqcn,
                'customImports' => [TolkiTypes::MODULE => [$tolkiName]],
            ], 'element' => null];
        }

        $model = [...ValueResult::unknown(), 'type' => $basename, 'modelFqcn' => $modelFqcn];

        // Laravel finds a collection for a list of keys, which the docblock spells as a conditional on the argument.
        if (in_array($terminal, self::MANY, true) || ($this->findsKeys($terminal) && ($call->getArgs()[0]->value ?? null) instanceof Array_)) {
            return ['result' => [...$model, 'type' => $basename.'[]'], 'element' => $model];
        }

        return [
            'result' => [...$model, 'type' => $basename.(in_array($terminal, self::NULLABLE_SINGLE, true) ? ' | null' : '')],
            'element' => null,
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
     * A query's `pluck('column')`, a list of what the column reads as on the model, or its `value('column')`, the
     * first row's or null; a pluck keyed by a second column declines.
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
