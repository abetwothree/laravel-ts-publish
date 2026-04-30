<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Inertia;

use AbeTwoThree\LaravelTsPublish\Concerns\ResolvesClassNames;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use ReflectionClass;

/**
 * Analyzes a controller method body to infer paginator-model relationships.
 *
 * Inspects assignment statements of the form:
 *   $var = SomeModel::chain()->paginate/simplePaginate/cursorPaginate()
 *
 * and pairs the variable names with prop keys from the Inertia::render() call
 * to produce a map of prop key → model FQCN, used to replace `<unknown>` generics.
 */
class ControllerPaginatorAnalyzer
{
    use ResolvesClassNames;

    /**
     * @var list<string>
     */
    private const PAGINATOR_METHODS = ['paginate', 'simplePaginate', 'cursorPaginate'];

    /**
     * @param  class-string  $controllerClass
     */
    public function __construct(
        protected string $controllerClass,
        protected string $methodName,
    ) {}

    /**
     * @var array{method: ClassMethod, finder: NodeFinder, varModelMap: array<string, class-string>}|null
     */
    private ?array $resolvedMethodContext = null;

    private bool $methodContextBuilt = false;

    /**
     * Analyze the controller method body to find paginator-model relationships.
     *
     * Returns an empty array when the class or method does not exist, the file
     * cannot be read, or no matching assignment patterns are found.
     *
     * For raw paginator props (`$var = Model::paginate()` → `'key' => $var`), the value
     * is the model FQCN so that `rewritePaginatorGenerics()` can fill in the generic type.
     * For `Resource::collection()` props, the value is the resource FQCN directly because
     * the type is `AnonymousResourceCollection<ResourceName>`, not `AnonymousResourceCollection<ModelName>`.
     *
     * @return array<string, class-string> prop key => model or resource FQCN
     */
    public function analyze(): array
    {
        $ctx = $this->getMethodContext();

        if ($ctx === null) {
            return [];
        }

        ['method' => $method, 'finder' => $finder, 'varModelMap' => $varModelMap] = $ctx;

        $propVarMap = $this->resolvePropVariables($method, $finder);

        /** @var array<string, class-string> $result */
        $result = [];

        foreach ($propVarMap as $propKey => $varName) {
            if (isset($varModelMap[$varName])) {
                $result[$propKey] = $varModelMap[$varName];
            }
        }

        $collectionProps = $this->resolveStaticCollectionProps($method, $finder, $varModelMap)['nonPaginated'];

        foreach ($collectionProps as $propKey => $resourceFqcn) {
            $result[$propKey] = $resourceFqcn;
        }

        return $result;
    }

    /**
     * Analyze the controller method body to find props that are resource objects
     * constructed with a paginated variable.
     *
     * Returns a map of prop key → resource FQCN for props of the form:
     *   'key' => new SomeResource($paginatedVar)
     * where `$paginatedVar` was assigned from a paginator method call.
     *
     * @return array<string, class-string<object>> prop key => resource FQCN
     */
    public function analyzePaginatedResourceProps(): array
    {
        $ctx = $this->getMethodContext();

        if ($ctx === null) {
            return [];
        }

        ['method' => $method, 'finder' => $finder, 'varModelMap' => $varModelMap] = $ctx;

        return $this->resolvePaginatedResourceConstructorProps($method, $finder, $varModelMap);
    }

    /**
     * Analyze the controller method body to find `Resource::collection($paginatedVar)` props.
     *
     * Returns a map of prop key → resource FQCN for props of the form:
     *   'key' => SomeResource::collection($paginatedVar)
     * where `$paginatedVar` was assigned from a paginator method call.
     *
     * @return array<string, class-string> prop key => resource FQCN
     */
    public function analyzePaginatedStaticCollectionProps(): array
    {
        $ctx = $this->getMethodContext();

        if ($ctx === null) {
            return [];
        }

        ['method' => $method, 'finder' => $finder, 'varModelMap' => $varModelMap] = $ctx;

        return $this->resolveStaticCollectionProps($method, $finder, $varModelMap)['paginated'];
    }

    /**
     * Get (and cache) the method context, returning null when the method cannot be resolved.
     *
     * @return array{method: ClassMethod, finder: NodeFinder, varModelMap: array<string, class-string>}|null
     */
    private function getMethodContext(): ?array
    {
        if (! $this->methodContextBuilt) {
            $this->resolvedMethodContext = $this->buildMethodContext();
            $this->methodContextBuilt = true;
        }

        return $this->resolvedMethodContext;
    }

    /**
     * Build the method context from the controller class and method name.
     *
     * Parses the controller file, resolves the method node, and builds the
     * variable-to-model map for use by both `analyze()` and `analyzePaginatedResourceProps()`.
     *
     * @return array{method: ClassMethod, finder: NodeFinder, varModelMap: array<string, class-string>}|null
     */
    private function buildMethodContext(): ?array
    {
        if (! class_exists($this->controllerClass)) {
            return null;
        }

        $reflection = new ReflectionClass($this->controllerClass);

        if (! $reflection->hasMethod($this->methodName)) {
            return null;
        }

        $fileName = $reflection->getFileName();

        if ($fileName === false) {
            return null; // @codeCoverageIgnore
        }

        $source = (string) file_get_contents($fileName);
        $stmts = $this->parseAndResolveAst($source);

        $finder = new NodeFinder;

        /** @var ClassMethod|null $method */
        $method = $finder->findFirst($stmts, function (Node $node): bool {
            return $node instanceof ClassMethod
                && $node->name->toString() === $this->methodName;
        });

        if (! $method instanceof ClassMethod || $method->stmts === null) {
            return null; // @codeCoverageIgnore
        }

        $varModelMap = $this->resolveVariableModels($method, $finder);

        return ['method' => $method, 'finder' => $finder, 'varModelMap' => $varModelMap];
    }

    /**
     * Scan the Inertia::render() props array for items of the form
     * `'key' => new SomeResource($paginatedVar)`, where `$paginatedVar` appears
     * in `$varModelMap` (i.e., it was assigned from a paginator method call).
     *
     * Only props with exactly one constructor argument that is a paginated variable
     * and whose class is a JsonResource subclass are included.
     *
     * @param  array<string, class-string>  $varModelMap  Variable name => model FQCN from paginator analysis.
     * @return array<string, class-string> prop key => resource FQCN
     */
    private function resolvePaginatedResourceConstructorProps(ClassMethod $method, NodeFinder $finder, array $varModelMap): array
    {
        if ($method->stmts === null) {
            return []; // @codeCoverageIgnore
        }

        /** @var StaticCall|null $renderCall */
        $renderCall = $finder->findFirst($method->stmts, function (Node $node): bool {
            return $node instanceof StaticCall
                && $node->class instanceof Name
                && str_ends_with($node->class->toString(), 'Inertia')
                && $node->name instanceof Identifier
                && $node->name->toString() === 'render';
        });

        if (! $renderCall instanceof StaticCall || count($renderCall->args) < 2) {
            return [];
        }

        $secondArg = $renderCall->args[1];

        if (! $secondArg instanceof Node\Arg || ! $secondArg->value instanceof Array_) {
            return [];
        }

        /** @var array<string, class-string> $map */
        $map = [];

        /** @var array<Expr\ArrayItem> $items */
        $items = $secondArg->value->items;

        foreach ($items as $item) {
            if (! $item->key instanceof String_) {
                continue;
            }

            if (! $item->value instanceof New_) {
                continue;
            }

            $newNode = $item->value;

            if (! $newNode->class instanceof Name) {
                continue;
            }

            if (count($newNode->args) !== 1) {
                continue;
            }

            $arg = $newNode->args[0];

            if (! $arg instanceof Node\Arg || ! $arg->value instanceof Variable || ! is_string($arg->value->name)) {
                continue;
            }

            $varName = $arg->value->name;

            if (! isset($varModelMap[$varName])) {
                continue;
            }

            $resourceFqcn = $newNode->class->toString();

            if (! class_exists($resourceFqcn) || ! is_a($resourceFqcn, JsonResource::class, true)) {
                continue;
            }

            /** @var class-string<JsonResource> $resourceFqcn */
            $map[$item->key->value] = $resourceFqcn;
        }

        return $map;
    }

    /**
     * Walk all assignments in the method to find variables assigned from
     * a paginator method call chain rooted at an Eloquent Model static call.
     *
     * Only direct chains are supported: `$var = Model::scope()->paginate()`.
     * Variable-indirection patterns (`$q = Post::query(); $q->paginate()`)
     * fall back to `<unknown>`.
     *
     * @return array<string, class-string> variable name => model FQCN
     */
    private function resolveVariableModels(ClassMethod $method, NodeFinder $finder): array
    {
        /** @var array<string, class-string> $varModelMap */
        $varModelMap = [];

        if ($method->stmts === null) {
            return $varModelMap; // @codeCoverageIgnore
        }

        /** @var list<Node> $found */
        $found = $finder->find($method->stmts, fn (Node $n) => $n instanceof Assign);

        foreach ($found as $assign) {
            if (! $assign instanceof Assign) {
                continue; // @codeCoverageIgnore
            }

            if (! $assign->var instanceof Variable || ! is_string($assign->var->name)) {
                continue;  // @codeCoverageIgnore
            }

            $varName = $assign->var->name;
            $rhs = $assign->expr;

            if (! $rhs instanceof MethodCall || ! $rhs->name instanceof Identifier) {
                continue;
            }

            if (! in_array($rhs->name->toString(), self::PAGINATOR_METHODS, true)) {
                continue;
            }

            $modelFqcn = $this->resolveModelFromChain($rhs->var);

            if ($modelFqcn !== null) {
                $varModelMap[$varName] = $modelFqcn;
            }
        }

        return $varModelMap;
    }

    /**
     * Recursively walk a method call chain back to its root StaticCall node
     * and return the FQCN if it resolves to an Eloquent Model subclass.
     *
     * Returns null when the root is not a StaticCall on a known Model subclass
     * or when any non-method-call node is encountered before the root.
     *
     * @return class-string|null
     */
    private function resolveModelFromChain(Expr $node): ?string
    {
        if ($node instanceof MethodCall) {
            return $this->resolveModelFromChain($node->var);
        }

        if (! $node instanceof StaticCall) {
            return null;
        }

        if (! $node->class instanceof Name) {
            return null;
        }

        $fqcn = $node->class->toString();

        if (! class_exists($fqcn) || ! is_a($fqcn, Model::class, true)) {
            return null;
        }

        /** @var class-string<Model> $fqcn */
        return $fqcn;
    }

    /**
     * Find the Inertia::render() call in the method body and extract a map of
     * prop key to variable name from its second argument.
     *
     * Handles array literal `['key' => $var]` and `compact('key')` forms.
     *
     * @return array<string, string> prop key => variable name
     */
    private function resolvePropVariables(ClassMethod $method, NodeFinder $finder): array
    {
        if ($method->stmts === null) {
            return []; // @codeCoverageIgnore
        }

        /** @var StaticCall|null $renderCall */
        $renderCall = $finder->findFirst($method->stmts, function (Node $node): bool {
            return $node instanceof StaticCall
                && $node->class instanceof Name
                && str_ends_with($node->class->toString(), 'Inertia')
                && $node->name instanceof Identifier
                && $node->name->toString() === 'render';
        });

        if (! $renderCall instanceof StaticCall || count($renderCall->args) < 2) {
            return [];
        }

        $secondArg = $renderCall->args[1];

        if (! $secondArg instanceof Node\Arg) {
            return [];
        }

        return $this->extractPropVarMap($secondArg->value);
    }

    /**
     * Extract a prop key => variable name map from an Inertia::render() props argument.
     *
     * Handles:
     *  - Array literal: `['posts' => $posts, ...]`
     *  - compact(): `compact('posts', 'users')`
     *
     * @return array<string, string>
     */
    private function extractPropVarMap(Expr $propsExpr): array
    {
        /** @var array<string, string> $map */
        $map = [];

        if ($propsExpr instanceof Array_) {
            foreach ($propsExpr->items as $item) {
                if (! $item->key instanceof String_) {
                    continue;
                }

                if (! $item->value instanceof Variable || ! is_string($item->value->name)) {
                    continue;
                }

                $map[$item->key->value] = $item->value->name;
            }
        }

        if (
            $propsExpr instanceof FuncCall
            && $propsExpr->name instanceof Name
            && $propsExpr->name->getLast() === 'compact'
        ) {
            foreach ($propsExpr->args as $arg) {
                if ($arg instanceof Node\Arg && $arg->value instanceof String_) {
                    $varName = $arg->value->value;
                    $map[$varName] = $varName;
                }
            }
        }

        return $map;
    }

    /**
     * Scan the Inertia::render() props array for items whose value is a
     * `SomeResource::collection(...)` static call, and return a map split into
     * two buckets: props whose collection argument is a paginated variable
     * (`paginated`) and all other collection props (`nonPaginated`).
     *
     * @param  array<string, class-string>  $varModelMap  Variable name => model FQCN (from paginator analysis).
     * @return array{nonPaginated: array<string, class-string>, paginated: array<string, class-string>}
     */
    private function resolveStaticCollectionProps(ClassMethod $method, NodeFinder $finder, array $varModelMap = []): array
    {
        if ($method->stmts === null) {
            return ['nonPaginated' => [], 'paginated' => []]; // @codeCoverageIgnore
        }

        /** @var StaticCall|null $renderCall */
        $renderCall = $finder->findFirst($method->stmts, function (Node $node): bool {
            return $node instanceof StaticCall
                && $node->class instanceof Name
                && str_ends_with($node->class->toString(), 'Inertia')
                && $node->name instanceof Identifier
                && $node->name->toString() === 'render';
        });

        if (! $renderCall instanceof StaticCall || count($renderCall->args) < 2) {
            return ['nonPaginated' => [], 'paginated' => []];
        }

        $secondArg = $renderCall->args[1];

        if (! $secondArg instanceof Node\Arg || ! $secondArg->value instanceof Array_) {
            return ['nonPaginated' => [], 'paginated' => []];
        }

        /** @var array<string, class-string> $nonPaginated */
        $nonPaginated = [];

        /** @var array<string, class-string> $paginated */
        $paginated = [];

        /** @var array<Expr\ArrayItem> $items */
        $items = $secondArg->value->items;

        foreach ($items as $item) {
            if (! $item->key instanceof String_) {
                continue;
            }

            if (! $item->value instanceof StaticCall) {
                continue;
            }

            if (! $item->value->name instanceof Identifier || $item->value->name->toString() !== 'collection') {
                continue;
            }

            if (! $item->value->class instanceof Name) {
                continue;
            }

            $resourceFqcn = $item->value->class->toString();

            if (! class_exists($resourceFqcn)) {
                continue;
            }

            /** @var class-string $resourceFqcn */
            $propKey = $item->key->value;

            // Determine if the first argument is a paginated variable
            $isPaginated = false;
            if ($item->value->args !== [] && $item->value->args[0] instanceof Node\Arg) {
                $firstArg = $item->value->args[0]->value;
                if ($firstArg instanceof Variable && is_string($firstArg->name) && isset($varModelMap[$firstArg->name])) {
                    $isPaginated = true;
                }
            }

            if ($isPaginated) {
                $paginated[$propKey] = $resourceFqcn;
            } else {
                $nonPaginated[$propKey] = $resourceFqcn;
            }
        }

        return ['nonPaginated' => $nonPaginated, 'paginated' => $paginated];
    }
}
