<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\CallChainWalker;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Support\PackageJson;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\Return_;
use ReflectionClass;

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
    /** A dev dependency, so named by string: an import would declare a requirement this package lacks. */
    private const TABLE_BASE = 'InertiaUI\\Table\\Table';

    private const TABLE_TYPE = 'TableResource';

    private const TABLE_PACKAGES = ['@inertiaui/table-vue', '@inertiaui/table-react'];

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [StaticCall::class, MethodCall::class, New_::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $table = resolve(CallChainWalker::class)->resolveRootClass($expr, self::TABLE_BASE, allowNew: true);
        $model = $table === null ? null : $this->tableModel($table);

        if ($model === null) {
            return null;
        }

        return [
            ...ValueResult::unknown(),
            'type' => self::TABLE_TYPE.'<'.class_basename($model).'>',
            'modelFqcn' => $model,
            'customImports' => [$this->tablePackage() => [self::TABLE_TYPE]],
        ];
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
