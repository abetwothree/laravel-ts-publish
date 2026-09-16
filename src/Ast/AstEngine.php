<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsInstanceofGuards;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Public entry point: `analyze()` — a class and a method in, properties and imports out.
 *
 * It and `AnalysisResult` are the engine's whole public surface. The other four methods here are
 * `@internal` like the rest of `src/Ast`: each traffics in a DTO whose shape tracks inference.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 */
final class AstEngine
{
    use CollectsInstanceofGuards;
    use CollectsLocalVarBindings;

    /** @var array<string, true> class@method@modelClass keys currently on the call stack — cycle guard. */
    private array $analyzing = [];

    /** @var array<string, MethodAnalysis> class@method@modelClass => completed analysis, for reuse. */
    private array $resultCache = [];

    /**
     * Analyze a method body's return shape. Resources get full resource semantics ('toArray'
     * default); any other class/method runs the same engine with the same handlers.
     *
     * Guarded against reentrant cycles (a spread reaching back to a class already mid-analysis) and
     * memoized per class@method@modelClass whenever the call has no active ancestor of its own, so
     * two resources spreading each other can't recurse until memory is exhausted.
     *
     * @param  class-string  $class
     * @param  class-string<Model>|null  $modelClass  Backing model for `$this->prop` resolution; null to skip.
     * @param  bool  $carriesImports  false when the caller keeps only the flattened types, never the FQCN channels
     *
     * @internal
     */
    public function analyzeMethod(
        string $class,
        string $method = 'toArray',
        ?string $modelClass = null,
        bool $carriesImports = true,
    ): MethodAnalysis {
        $reflection = new ReflectionClass($class);

        if ($modelClass === null && is_a($class, JsonResource::class, true)) {
            $modelClass = resolve(ModelClassResolver::class)->resolve($reflection);
        }

        $key = $class.'@'.$method.'@'.($modelClass ?? '').($carriesImports ? '' : '@importless');

        if (isset($this->resultCache[$key])) {
            return clone $this->resultCache[$key];
        }

        // Already on the stack: a self-spread or a cycle through other classes. Contribute nothing
        // rather than re-entering — the caller's own merge() treats an empty analysis as a no-op.
        if (isset($this->analyzing[$key])) {
            return new MethodAnalysis;
        }

        // An active ancestor may itself be cut short by a cycle closing back through it, so what we
        // compute here can be a truncated shape — caching that would make the result depend on which
        // entry point ran first. Only the outermost call in its chain is safe to memoize.
        $hasActiveAncestor = $this->analyzing !== [];

        $this->analyzing[$key] = true;

        try {
            $analysis = new ResourceAstAnalyzer($reflection, $modelClass, $method, carriesImports: $carriesImports)->analyze();
        } finally {
            unset($this->analyzing[$key]);
        }

        if ($hasActiveAncestor) {
            return $analysis;
        }

        $this->resultCache[$key] = $analysis;

        return clone $analysis;
    }

    /**
     * Analyze a method and resolve its imports in one call — the whole contract a consumer needs,
     * except for a $wrap = null collection, whose whole answer is MethodAnalysis::$flatTypeAlias.
     *
     * The three fields agree with each other: same-basename imports are aliased apart and the
     * property types spell the aliases, an `EnumResource::make()` property is already wrapped as
     * `AsEnum<typeof Const>`, and nothing is imported that no property type names.
     *
     * `$fromNamespacePath` is the generated file's own namespace path, so relative import paths
     * resolve from where the file will live; pass '' for a file at the output root.
     *
     * Consumers that rewrite channels before importing (Inertia shared data, model metadata,
     * broadcast events) keep calling analyzeMethod() and AnalysisImports::build() themselves; this
     * method is the answer for everyone else.
     *
     * @param  class-string  $class
     * @param  class-string<Model>|null  $modelClass
     */
    public function analyze(
        string $class,
        string $method = 'toArray',
        ?string $modelClass = null,
        string $fromNamespacePath = '',
    ): AnalysisResult {
        return new AnalysisComposer()->compose(
            $this->analyzeMethod($class, $method, $modelClass),
            $fromNamespacePath,
        );
    }

    /**
     * Build the starting scope for a located method: its subject, the classes its parameters bind,
     * and the single-write local variables its body assigns.
     *
     * A route-bound `Post $post` and an injected `Request $request` are both parameter facts the
     * resource path never had, which is why they are seeded here rather than inside the analyzer.
     *
     * @internal
     */
    public function bindingsFor(MethodContext $context): AnalysisScope
    {
        $scope = new AnalysisScope($context->reflection);
        $methodName = $context->method->name->toString();

        if ($context->reflection->hasMethod($methodName)) {
            foreach ($context->reflection->getMethod($methodName)->getParameters() as $parameter) {
                $type = $parameter->getType();

                if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                    continue;
                }

                $class = $type->getName();

                if (is_a($class, Model::class, true)) {
                    /** @var class-string<Model> $class */
                    $scope->varModelBindings[$parameter->getName()] = $class;
                } elseif (is_a($class, Request::class, true)) {
                    /** @var class-string<Request> $class */
                    $scope->requestVarNames[$parameter->getName()] = $class;
                }
            }
        }

        $this->collectLocalVarBindings($context->method->stmts ?? [], $scope);
        $this->collectInstanceofGuards($context->method->stmts ?? [], $scope);

        return $scope;
    }

    /**
     * Resolve one closure (an accessor getter, or a method body wrapped as one) against a model subject.
     *
     * @param  class-string<Model>  $modelClass
     * @param  bool  $carriesImports  false when an analysis that carries no import reads the accessor
     * @return ValueExpressionResult
     *
     * @internal
     */
    public function analyzeModelClosure(
        string $modelClass,
        ClosureExpr|ArrowFunction $closure,
        MethodContext $context,
        bool $carriesImports = true,
    ): array {
        $scope = $this->bindingsFor($context);

        // A trait-declared accessor still reads `$this` as the model that uses the trait.
        $scope->subjectReflection = self::genericReflection($modelClass);
        $scope->modelClass = $modelClass;
        $scope->carriesImports = $carriesImports;

        $analyzer = new ResourceAstAnalyzer(
            $scope->subjectReflection,
            $modelClass,
            $context->method->name->toString(),
            ResourceExpressionHandlers::forModelClosures(),
            $scope,
            $context,
        );

        return $analyzer->resolve($closure);
    }

    /**
     * Analyze a class's public properties — promoted constructor params AND class-body declarations,
     * `@var` docblock first, native type second — into properties + enum/model FQCN channels.
     * A property that is neither promoted nor defaulted is optional: `json_encode()` omits it when it
     * was never assigned. Reflection cannot see a constructor assignment, so a property a constructor
     * always assigns still renders `?:` — `DeclaredPropsEvent::$label` is exactly that case.
     *
     * @param  class-string  $class
     *
     * @internal
     */
    public function analyzePublicProperties(string $class): MethodAnalysis
    {
        $reflection = new ReflectionClass($class);
        $traitProperties = $this->traitPropertyNames($reflection);
        $resolver = resolve(SubjectPropertyTypeResolver::class);
        $analysis = new MethodAnalysis;

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();

            if (in_array($name, $traitProperties, true)) {
                continue;
            }

            $result = $resolver->resolve($reflection, $name) ?? ValueResult::unknown();

            // json_encode() omits a typed property never assigned; a promoted or defaulted one is always present.
            $analysis->addProperty($name, $result, optional: ! $property->hasDefaultValue() && ! $property->isPromoted());
        }

        return $analysis;
    }

    /**
     * A reflection typed as the invariant `ReflectionClass<object>` the scope's own property declares.
     *
     * @param  class-string  $className
     * @return ReflectionClass<object>
     */
    private static function genericReflection(string $className): ReflectionClass
    {
        return new ReflectionClass($className);
    }

    /**
     * Names of every property a used trait declares, transitively.
     *
     * Trait properties are reflected as the using class's own, so only the name distinguishes them;
     * a #[TsExtends] trait already supplies its fields, and emitting them again duplicates a field.
     *
     * @param  ReflectionClass<object>  $reflection
     * @return list<string>
     */
    private function traitPropertyNames(ReflectionClass $reflection): array
    {
        $names = [];
        $pending = $reflection->getTraits();

        while ($pending !== []) {
            $trait = array_shift($pending);

            foreach ($trait->getProperties() as $property) {
                $names[] = $property->getName();
            }

            $pending = [...$pending, ...$trait->getTraits()];
        }

        return array_values(array_unique($names));
    }
}
