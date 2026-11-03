<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\DocblockTags;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\CallChainWalker;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Support\PackageJson;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Return_;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * An Inertia UI Table, `PostTable::make()->defaultSort('-id')` or `new PostTable`, which Inertia sends as its
 * `toArray()`: the package's `TableResource` over the table's model. The table is read, never instantiated, since
 * loading its export dependencies can fatal.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class InertiaTableHandler implements ExpressionHandler
{
    /** Marks a value the table reader this handler replaced typed as a table, a first render call's props item. */
    public const string TYPED_AS_TABLE = 'typedAsTable';

    /** A dev dependency, so named by string: an import would declare a requirement this package lacks. */
    private const TABLE_BASE = 'InertiaUI\\Table\\Table';

    private const TABLE_TYPE = 'TableResource';

    private const TABLE_PACKAGES = ['@inertiaui/table-vue', '@inertiaui/table-react'];

    /** @var array<int, true> Chains past the table this handler declines while the rest of the profile reads them. */
    private array $readingPast = [];

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [StaticCall::class, MethodCall::class, New_::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $table = isset($this->readingPast[spl_object_id($expr)])
            ? null
            : resolve(CallChainWalker::class)->resolveRootClass($expr, self::TABLE_BASE, allowNew: true);
        $model = $table === null ? null : $this->tableModel($table);

        if ($table === null || $model === null) {
            return null;
        }

        $leaving = $this->callLeavingTable($expr, $table);
        $past = $leaving === null ? null : $this->readPast($expr, $leaving, $table, $model, $engine);

        // A chain past the table that nothing reads keeps the table type where the reader before this handler gave it.
        if ($past !== null && ($past['type'] !== 'unknown' || $expr->getAttribute(self::TYPED_AS_TABLE) !== true)) {
            return $past;
        }

        $type = self::TABLE_TYPE.'<'.class_basename($model).'>';

        return [
            ...ValueResult::unknown(),
            'type' => $this->mayReturnNull($expr, $table) ? ValueResult::withNullArm($type) : $type,
            'modelFqcn' => $model,
            'customImports' => [$this->tablePackage() => [self::TABLE_TYPE]],
        ];
    }

    /**
     * The call nearest the root where the chain stops returning the table; null where every call returns it.
     *
     * @param  class-string  $table
     */
    private function callLeavingTable(Expr $expr, string $table): MethodCall|StaticCall|null
    {
        $leaving = null;

        while ($expr instanceof MethodCall || $expr instanceof StaticCall) {
            if (! $expr->name instanceof Identifier || $this->tableReturnsNull($table, $expr->name->toString()) === null) {
                $leaving = $expr;
            }

            $expr = $expr instanceof MethodCall ? $expr->var : null;
        }

        return $leaving;
    }

    /**
     * What the rest of the profile reads a chain past the table as.
     *
     * Past the table's own `query()`, it is the same chain on `query()` of the model that `query()` queries, else the
     * table's; past any other call, the chain as it stands, with this handler declining it.
     *
     * @param  class-string  $table
     * @param  class-string<Model>  $model
     * @return ValueExpressionResult
     */
    private function readPast(
        Expr $expr,
        MethodCall|StaticCall $leaving,
        string $table,
        string $model,
        ExpressionEngine $engine,
    ): array {
        if ($leaving instanceof MethodCall && $this->callsQuery($leaving)) {
            return $engine->resolve($this->onModelQuery($expr, $leaving, $this->queryModel($table) ?? $model));
        }

        $this->readingPast[spl_object_id($expr)] = true;

        try {
            return $engine->resolve($expr);
        } finally {
            unset($this->readingPast[spl_object_id($expr)]);
        }
    }

    /**
     * Whether a call is the table's own argument-less `query()`.
     */
    private function callsQuery(MethodCall $call): bool
    {
        return $call->name instanceof Identifier
            && strtolower($call->name->toString()) === 'query'
            && ! $call->isFirstClassCallable()
            && $call->getArgs() === [];
    }

    /**
     * The chain with the table's `query()` call replaced by the model's own.
     *
     * @param  class-string<Model>  $model
     */
    private function onModelQuery(Expr $expr, MethodCall $query, string $model): Expr
    {
        if ($expr === $query) {
            return new StaticCall(new FullyQualified($model), 'query', [], $query->getAttributes());
        }

        if (! $expr instanceof MethodCall) {
            return $expr;
        }

        $receiver = $this->onModelQuery($expr->var, $query, $model);

        return new MethodCall($receiver, $expr->name, $expr->args, $expr->getAttributes());
    }

    /**
     * Whether the chain's last call is a table method that may return null, so the chain may send null.
     *
     * A null earlier in the chain throws at the next call, so only the last call's null reaches the response.
     *
     * @param  class-string  $table
     */
    private function mayReturnNull(Expr $expr, string $table): bool
    {
        return ($expr instanceof MethodCall || $expr instanceof StaticCall)
            && $expr->name instanceof Identifier
            && $this->tableReturnsNull($table, $expr->name->toString()) === true;
    }

    /**
     * Whether a table method that returns the table may return null too, natively or, with no native type, by
     * `@return`; null when it does not return the table.
     *
     * @param  class-string  $table
     */
    private function tableReturnsNull(string $table, string $method): ?bool
    {
        if (! method_exists($table, $method)) {
            return null;
        }

        $reflection = new ReflectionMethod($table, $method);
        $type = $reflection->getReturnType();

        if ($type !== null) {
            return $type instanceof ReflectionNamedType && $this->namesTable($type->getName()) ? $type->allowsNull() : null;
        }

        $declared = DocblockTags::type((string) $reflection->getDocComment(), 'return') ?? '';
        $arms = LaravelTsPublish::splitPhpDocUnionType(ltrim($declared, '?'));
        $named = array_values(array_filter($arms, fn (string $arm): bool => strtolower($arm) !== 'null'));

        if (count($named) !== 1 || ! $this->namesDeclaredTable($named[0], $reflection)) {
            return null;
        }

        return str_starts_with($declared, '?') || count($named) < count($arms);
    }

    /**
     * Whether a `@return` arm names the table: `$this`, `static`, `self` or a table class, a short or aliased name read
     * against the use statements of the file that declares the method.
     */
    private function namesDeclaredTable(string $arm, ReflectionMethod $method): bool
    {
        if ($arm === '$this' || $this->namesTable($arm)) {
            return true;
        }

        $file = LaravelTsPublish::methodDeclaringFileClass($method);

        return $this->namesTable(LaravelTsPublish::resolveDocblockTypeName(
            $arm,
            LaravelTsPublish::parseFileUseStatements($file),
            $file->getNamespaceName(),
        ));
    }

    /**
     * Whether a return type's name is the table itself or a table class.
     */
    private function namesTable(string $name): bool
    {
        return in_array(strtolower($name), ['static', 'self'], true)
            || is_a(ltrim($name, '\\'), self::TABLE_BASE, true);
    }

    /**
     * The table's model: its `$resource` default, else the model its `query()` returns a query of.
     *
     * @param  class-string  $table
     * @return class-string<Model>|null
     */
    private function tableModel(string $table): ?string
    {
        return $this->resourceModel(new ReflectionClass($table)) ?? $this->queryModel($table);
    }

    /**
     * The model `protected ?string $resource = Post::class;` names: the default holds it, the type says only `?string`.
     *
     * @param  ReflectionClass<object>  $reflection
     * @return class-string<Model>|null
     */
    private function resourceModel(ReflectionClass $reflection): ?string
    {
        $property = $reflection->hasProperty('resource') ? $reflection->getProperty('resource') : null;
        $default = $property?->hasDefaultValue() ? $property->getDefaultValue() : null;

        if (! is_string($default) || ! class_exists($default) || ! is_a($default, Model::class, true)) {
            return null;
        }

        DependencyRecorder::recordClass($default);

        return $default;
    }

    /**
     * The model a `query()` the table declares returns: `Post::query()`, `Post::class` or a query chain on it.
     *
     * @param  class-string  $table
     * @return class-string<Model>|null
     */
    private function queryModel(string $table): ?string
    {
        if (! method_exists($table, 'query')) {
            return null;
        }

        $walker = resolve(CallChainWalker::class);

        foreach (resolve(MethodLocator::class)->locateDeclared($table, 'query')?->method->stmts ?? [] as $stmt) {
            if ($stmt instanceof Return_ && $stmt->expr instanceof Expr) {
                return $walker->resolveRootClass($stmt->expr, Model::class, allowClassConst: true);
            }
        }

        return null;
    }

    /**
     * The npm package the `TableResource` type is imported from: the configured one, else the first installed.
     */
    private function tablePackage(): string
    {
        $configured = config('ts-publish.inertia.ui_table_package');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return PackageJson::firstInstalled(self::TABLE_PACKAGES) ?? self::TABLE_PACKAGES[0];
    }
}
