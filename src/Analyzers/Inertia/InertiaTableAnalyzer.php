<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Inertia;

use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Concerns\ResolvesClassNames;
use AbeTwoThree\LaravelTsPublish\Support\PackageJson;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionType;

/**
 * Statically detects Inertia UI Table props on a controller action and builds
 * page-prop type data (component, page type, model FQCNs, table-package import)
 * without instantiating the table or calling its Arrayable::toArray() method.
 *
 * @phpstan-type TablePageData = array{component: string, pageType: string, classFqcns: list<class-string>, externalImports: array<string, list<string>>}
 */
class InertiaTableAnalyzer
{
    use ResolvesClassNames;

    private const TABLE_BASE = 'InertiaUI\\Table\\Table';

    private const TABLE_TYPE = 'TableResource';

    private const TABLE_PACKAGES = ['@inertiaui/table-vue', '@inertiaui/table-react'];

    /**
     * Resolve the Inertia component name from a controller action's first
     * `Inertia::render()` argument without instantiating any objects.
     *
     * Returns `null` when the action cannot be found, contains no render call,
     * or the component argument is not a string literal.
     */
    public function resolveComponent(string $uses): ?string
    {
        if (! str_contains($uses, '@')) {
            return null;
        }

        [$controllerClass, $methodName] = explode('@', $uses, 2);

        if (! class_exists($controllerClass)) {
            return null;
        }

        /** @var class-string $controllerClass */
        $context = $this->methodContext($controllerClass, $methodName);

        if ($context === null) {
            return null;
        }

        ['method' => $method, 'finder' => $finder] = $context;
        $renderCall = $this->findInertiaRenderCall($method, $finder);

        if ($renderCall === null) {
            return null;
        }

        return $this->resolveComponentName($renderCall);
    }

    /**
     * Determine whether analyzing this route would parse a file containing an
     * `InertiaUI\Table\Table` subclass reference.
     *
     * Two taint sources are checked in order:
     *   (a) the controller file itself — any `StaticCall`/`New_` rooted at a
     *       `Table` subclass anywhere in the file taints every action in it;
     *   (b) any class reached via a `$this->property->method(...)` argument in
     *       the action's `Inertia::render()` call — if that resource file
     *       contains a table reference the route is tainted.
     *
     * Only `class_exists` and `is_a` are used to test class membership; no
     * table method is invoked and no `toArray()` is triggered.
     */
    public function isTainted(string $uses): bool
    {
        if (! str_contains($uses, '@')) {
            return false;
        }

        [$controllerClass, $methodName] = explode('@', $uses, 2);

        if (! class_exists($controllerClass)) {
            return false;
        }

        /** @var class-string $controllerClass */
        $context = $this->methodContext($controllerClass, $methodName);

        if ($context === null) {
            return false;
        }

        ['reflection' => $reflection, 'method' => $method, 'finder' => $finder] = $context;

        // (a) Taint from the controller file itself — scan all stmts, not just the target method.
        $controllerFile = $reflection->getFileName();

        if ($controllerFile !== false) {
            $fileStmts = $this->parseAndResolveAst((string) file_get_contents($controllerFile));

            if ($this->containsTableReference($fileStmts)) {
                return true;
            }
        }

        // (c) Taint from a table-bearing controller dependency (constructor param or
        // typed property). Ranger parses the whole controller file, including the
        // constructor's injected resource, so any action on such a controller can reach
        // the table — even ones with no Inertia::render() (e.g. store()/update()).
        if ($this->controllerDependsOnTable($reflection)) {
            return true;
        }

        // (b) Taint from a resource/service class resolved via $this->property->method(...).
        $renderCall = $this->findInertiaRenderCall($method, $finder);

        if ($renderCall === null) {
            return false;
        }

        $secondArg = $renderCall->args[1] ?? null;

        if (! $secondArg instanceof Node\Arg || ! $secondArg->value instanceof MethodCall) {
            return false;
        }

        $call = $secondArg->value;

        if (! $call->name instanceof Identifier) {
            return false;
        }

        $resourceClass = $this->resolveThisPropertyClass($reflection, $call->var);

        if ($resourceClass === null) {
            return false;
        }

        $resourceContext = $this->methodContext($resourceClass, $call->name->toString());

        if ($resourceContext === null) {
            return false;
        }

        /** @var ReflectionClass<object> $resourceReflection */
        $resourceReflection = $resourceContext['reflection'];
        $resourceFile = $resourceReflection->getFileName();

        if ($resourceFile === false) {
            return false;
        }

        $resourceStmts = $this->parseAndResolveAst((string) file_get_contents($resourceFile));

        return $this->containsTableReference($resourceStmts);
    }

    /**
     * Whether the controller depends on a table-bearing class via a constructor
     * parameter type or a typed property — Surveyor resolves these when it parses
     * the controller file, so they taint every action on the controller.
     *
     * @param  ReflectionClass<object>  $reflection
     */
    protected function controllerDependsOnTable(ReflectionClass $reflection): bool
    {
        /** @var array<class-string, true> $candidates */
        $candidates = [];

        $constructor = $reflection->getConstructor();

        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $parameter) {
                $this->collectClassType($parameter->getType(), $candidates);
            }
        }

        foreach ($reflection->getProperties() as $property) {
            $this->collectClassType($property->getType(), $candidates);
        }

        foreach (array_keys($candidates) as $class) {
            if ($this->classFileContainsTable($class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add a non-builtin, existing class type to the candidate set.
     *
     * @param  array<class-string, true>  $candidates
     */
    protected function collectClassType(?ReflectionType $type, array &$candidates): void
    {
        if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return;
        }

        $class = $type->getName();

        if (class_exists($class)) {
            /** @var class-string $class */
            $candidates[$class] = true;
        }
    }

    /**
     * Whether the file declaring the given class references an Inertia UI Table
     * subclass. Reflection + `containsTableReference()` only; no table is evaluated.
     *
     * @param  class-string  $class
     */
    protected function classFileContainsTable(string $class): bool
    {
        $file = (new ReflectionClass($class))->getFileName();

        if ($file === false) {
            return false;
        }

        DependencyRecorder::record($file);

        return $this->containsTableReference($this->parseAndResolveAst((string) file_get_contents($file)));
    }

    /**
     * Analyze a controller action for Inertia UI Table props without evaluating
     * the table object or its Arrayable::toArray() method.
     *
     * @return TablePageData|null
     */
    public function analyze(string $uses): ?array
    {
        if (! str_contains($uses, '@')) {
            return null;
        }

        [$controllerClass, $methodName] = explode('@', $uses, 2);

        if (! class_exists($controllerClass)) {
            return null;
        }

        /** @var class-string $controllerClass */
        $context = $this->methodContext($controllerClass, $methodName);

        if ($context === null) {
            return null;
        }

        ['reflection' => $reflection, 'method' => $method, 'finder' => $finder] = $context;
        $renderCall = $this->findInertiaRenderCall($method, $finder);

        if ($renderCall === null) {
            return null;
        }

        $component = $this->resolveComponentName($renderCall);

        if ($component === null) {
            return null;
        }

        $props = $this->resolvePropsFromRenderCall($reflection, $renderCall);

        if ($props === []) {
            return null;
        }

        $package = $this->resolveTablePackage();
        $parts = [];
        $modelFqcns = [];

        foreach ($props as $key => $modelFqcn) {
            $parts[] = $key.': '.self::TABLE_TYPE.'<'.class_basename($modelFqcn).'>';

            if (! in_array($modelFqcn, $modelFqcns, true)) {
                $modelFqcns[] = $modelFqcn;
            }
        }

        return [
            'component' => $component,
            'pageType' => 'Inertia.SharedData & { '.implode(', ', $parts).' }',
            'classFqcns' => $modelFqcns,
            'externalImports' => [$package => [self::TABLE_TYPE]],
        ];
    }

    /**
     * Resolve the npm package that exports the table `TableResource` type.
     *
     * Priority: config value → package.json detection → @inertiaui/table-vue.
     */
    protected function resolveTablePackage(): string
    {
        $configured = config('ts-publish.inertia.ui_table_package');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return PackageJson::firstInstalled(self::TABLE_PACKAGES) ?? self::TABLE_PACKAGES[0];
    }

    /**
     * Reflect a class method, record its file as a cache dependency, and parse
     * its name-resolved AST for further static inspection.
     *
     * @param  class-string  $class
     * @return array{reflection: ReflectionClass<object>, method: ClassMethod, finder: NodeFinder}|null
     */
    protected function methodContext(string $class, string $methodName): ?array
    {
        /** @var ReflectionClass<object> $reflection */
        $reflection = new ReflectionClass($class);

        if (! $reflection->hasMethod($methodName)) {
            return null;
        }

        $file = $reflection->getFileName();

        if ($file === false) {
            return null; // @codeCoverageIgnore
        }

        DependencyRecorder::record((string) $file);

        $stmts = $this->parseAndResolveAst((string) file_get_contents($file));
        $finder = new NodeFinder;

        /** @var ClassMethod|null $method */
        $method = $finder->findFirst($stmts, fn (Node $node): bool => $node instanceof ClassMethod && $node->name->toString() === $methodName);

        if (! $method instanceof ClassMethod || $method->stmts === null) {
            return null;
        }

        return ['reflection' => $reflection, 'method' => $method, 'finder' => $finder];
    }

    /**
     * Find the first Inertia::render(...) call in a method.
     */
    protected function findInertiaRenderCall(ClassMethod $method, NodeFinder $finder): ?StaticCall
    {
        if ($method->stmts === null) {
            return null;
        }

        /** @var StaticCall|null $call */
        $call = $finder->findFirst($method->stmts, function (Node $node): bool {
            return $node instanceof StaticCall
                && $node->class instanceof Name
                && str_ends_with($node->class->toString(), 'Inertia')
                && $node->name instanceof Identifier
                && $node->name->toString() === 'render';
        });

        return $call;
    }

    /**
     * Resolve the component string from Inertia::render('Component', ...).
     */
    protected function resolveComponentName(StaticCall $renderCall): ?string
    {
        $firstArg = $renderCall->args[0] ?? null;

        if (! $firstArg instanceof Node\Arg || ! $firstArg->value instanceof String_) {
            return null;
        }

        return $firstArg->value->value;
    }

    /**
     * Resolve table props from the second argument of Inertia::render(...).
     *
     * @param  ReflectionClass<object>  $controllerReflection
     * @return array<string, class-string<Model>>
     */
    protected function resolvePropsFromRenderCall(ReflectionClass $controllerReflection, StaticCall $renderCall): array
    {
        $secondArg = $renderCall->args[1] ?? null;

        if (! $secondArg instanceof Node\Arg) {
            return [];
        }

        return $this->resolvePropsExpression($controllerReflection, $secondArg->value);
    }

    /**
     * Resolve table props from a props expression: an inline array literal or a
     * service-method call such as $this->resource->index($request).
     *
     * @param  ReflectionClass<object>  $controllerReflection
     * @return array<string, class-string<Model>>
     */
    protected function resolvePropsExpression(ReflectionClass $controllerReflection, Expr $expr): array
    {
        if ($expr instanceof Array_) {
            return $this->resolvePropsArray($expr);
        }

        if ($expr instanceof MethodCall) {
            return $this->resolvePropsFromServiceMethod($controllerReflection, $expr);
        }

        return [];
    }

    /**
     * Map each string-keyed array item to its backing model class when the
     * item value is an Inertia UI Table expression.
     *
     * @return array<string, class-string<Model>>
     */
    protected function resolvePropsArray(Array_ $array): array
    {
        /** @var array<string, class-string<Model>> $props */
        $props = [];

        foreach ($array->items as $item) {
            if (! $item->key instanceof String_) {
                continue;
            }

            $modelFqcn = $this->resolveModelFromTableExpression($item->value);

            if ($modelFqcn !== null) {
                $props[$item->key->value] = $modelFqcn;
            }
        }

        return $props;
    }

    /**
     * Resolve table props returned from a service method invoked on a typed
     * controller property (e.g. $this->resource->index()).
     *
     * @param  ReflectionClass<object>  $controllerReflection
     * @return array<string, class-string<Model>>
     */
    protected function resolvePropsFromServiceMethod(ReflectionClass $controllerReflection, MethodCall $call): array
    {
        if (! $call->name instanceof Identifier) {
            return [];
        }

        $serviceClass = $this->resolveThisPropertyClass($controllerReflection, $call->var);

        if ($serviceClass === null) {
            return [];
        }

        $context = $this->methodContext($serviceClass, $call->name->toString());

        if ($context === null || $context['method']->stmts === null) {
            return [];
        }

        foreach ($context['method']->stmts as $stmt) {
            if ($stmt instanceof Return_ && $stmt->expr instanceof Array_) {
                return $this->resolvePropsArray($stmt->expr);
            }
        }

        return [];
    }

    /**
     * Resolve the class of a $this->property reference from the controller's
     * typed property declaration.
     *
     * @param  ReflectionClass<object>  $reflection
     * @return class-string|null
     */
    protected function resolveThisPropertyClass(ReflectionClass $reflection, Expr $expr): ?string
    {
        if (! $expr instanceof PropertyFetch || ! $expr->var instanceof Variable || $expr->var->name !== 'this') {
            return null;
        }

        if (! $expr->name instanceof Identifier) {
            return null;
        }

        $property = $expr->name->toString();

        if (! $reflection->hasProperty($property)) {
            return null;
        }

        $type = $reflection->getProperty($property)->getType();

        if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $class = $type->getName();

        if (! class_exists($class)) {
            return null;
        }

        /** @var class-string $class */
        return $class;
    }

    /**
     * Walk a table prop expression (e.g. PostTable::make()->defaultSort(...)) to
     * its root table class and resolve that table's backing model.
     *
     * @return class-string<Model>|null
     */
    protected function resolveModelFromTableExpression(Expr $expr): ?string
    {
        if ($expr instanceof MethodCall) {
            return $this->resolveModelFromTableExpression($expr->var);
        }

        $tableFqcn = null;

        if ($expr instanceof StaticCall && $expr->class instanceof Name) {
            $tableFqcn = $expr->class->toString();
        }

        if ($expr instanceof New_ && $expr->class instanceof Name) {
            $tableFqcn = $expr->class->toString();
        }

        if (! is_string($tableFqcn) || ! class_exists($tableFqcn) || ! is_a($tableFqcn, self::TABLE_BASE, true)) {
            return null;
        }

        DependencyRecorder::recordClass($tableFqcn);

        return $this->resolveTableModel($tableFqcn);
    }

    /**
     * Resolve the backing Eloquent model for a table without instantiating it.
     *
     * Inertia UI Table tables declare their model as the default value of the
     * `$resource` property (`protected ?string $resource = Post::class;`) or by
     * returning `Post::query()` from a `query()` method. Both are read
     * statically and never touch table state or `toArray()`.
     *
     * @param  class-string  $tableFqcn
     * @return class-string<Model>|null
     */
    protected function resolveTableModel(string $tableFqcn): ?string
    {
        /** @var ReflectionClass<object> $reflection */
        $reflection = new ReflectionClass($tableFqcn);

        return $this->resolveModelFromResourceProperty($reflection)
            ?? $this->resolveModelFromQueryMethod($reflection);
    }

    /**
     * Read the model FQCN from `protected ?string $resource = Model::class;`.
     *
     * The model is the property's *default value* (a compile-time `Model::class`
     * constant), not its `?string` type hint, so we read the default value via
     * reflection rather than the type. This is side-effect-free.
     *
     * @param  ReflectionClass<object>  $reflection
     * @return class-string<Model>|null
     */
    protected function resolveModelFromResourceProperty(ReflectionClass $reflection): ?string
    {
        if (! $reflection->hasProperty('resource')) {
            return null;
        }

        $property = $reflection->getProperty('resource');

        if (! $property->hasDefaultValue()) {
            return null;
        }

        $default = $property->getDefaultValue();

        if (! is_string($default) || ! class_exists($default) || ! is_a($default, Model::class, true)) {
            return null;
        }

        /** @var class-string<Model> $default */
        DependencyRecorder::recordClass($default);

        return $default;
    }

    /**
     * Read the model FQCN from a statically-analyzable `query()` method that
     * returns `Model::query()`, `Model::class`, or a query chain rooted at a model.
     *
     * @param  ReflectionClass<object>  $reflection
     * @return class-string<Model>|null
     */
    protected function resolveModelFromQueryMethod(ReflectionClass $reflection): ?string
    {
        if (! $reflection->hasMethod('query')) {
            return null;
        }

        $context = $this->methodContext($reflection->getName(), 'query');

        if ($context === null || $context['method']->stmts === null) {
            return null;
        }

        foreach ($context['method']->stmts as $stmt) {
            if ($stmt instanceof Return_ && $stmt->expr instanceof Expr) {
                return $this->resolveModelFromQueryExpression($stmt->expr);
            }
        }

        return null;
    }

    /**
     * Resolve a model class from a query() return expression such as
     * Model::query(), Model::class, or a query chain rooted at the model.
     *
     * @return class-string<Model>|null
     */
    protected function resolveModelFromQueryExpression(Expr $expr): ?string
    {
        if ($expr instanceof MethodCall) {
            return $this->resolveModelFromQueryExpression($expr->var);
        }

        if ($expr instanceof StaticCall && $expr->class instanceof Name) {
            $fqcn = $expr->class->toString();

            if (class_exists($fqcn) && is_a($fqcn, Model::class, true)) {
                /** @var class-string<Model> $fqcn */
                DependencyRecorder::recordClass($fqcn);

                return $fqcn;
            }
        }

        if ($expr instanceof ClassConstFetch && $expr->class instanceof Name && $expr->name instanceof Identifier && $expr->name->toString() === 'class') {
            $fqcn = $expr->class->toString();

            if (class_exists($fqcn) && is_a($fqcn, Model::class, true)) {
                /** @var class-string<Model> $fqcn */
                DependencyRecorder::recordClass($fqcn);

                return $fqcn;
            }
        }

        return null;
    }

    /**
     * Report whether a parsed AST contains any `StaticCall` or `New_` node whose
     * resolved class name is a subclass of `InertiaUI\Table\Table`.
     *
     * Uses only `class_exists` and `is_a` — no table method is invoked.
     *
     * @param  array<Node>  $stmts
     */
    protected function containsTableReference(array $stmts): bool
    {
        $finder = new NodeFinder;

        /** @var array<StaticCall|New_> $candidates */
        $candidates = $finder->find($stmts, fn (Node $node): bool => $node instanceof StaticCall || $node instanceof New_);

        foreach ($candidates as $node) {
            $className = null;

            if ($node instanceof StaticCall && $node->class instanceof Name) {
                $className = $node->class->toString();
            }

            if ($node instanceof New_ && $node->class instanceof Name) {
                $className = $node->class->toString();
            }

            if (is_string($className) && class_exists($className) && is_a($className, self::TABLE_BASE, true)) {
                return true;
            }
        }

        return false;
    }
}
