<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\FunctionLike;
use ReflectionClass;
use ReflectionProperty;

/**
 * Public entry point: `analyze()` — a class and a method in, properties and imports out.
 *
 * It and `AnalysisResult` are the engine's whole public surface. The other five methods here are
 * `@internal` like the rest of `src/Ast`: each traffics in a DTO whose shape tracks inference.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type PassedBinding from ParameterBinder
 */
final class AstEngine
{
    use CollectsLocalVarBindings;
    use InspectsAstNodes;

    /**
     * Analyze a method body's return shape: resources get full resource semantics ('toArray' default), and any other
     * class runs the same engine without the `when*()` family. Cycle-guarded, and memoized for the run through
     * AnalysisMemo.
     *
     * @param  class-string  $class
     * @param  class-string<Model>|null  $modelClass  Backing model for `$this->prop` resolution; null to skip.
     * @param  bool  $carriesImports  false when the caller keeps only the flattened types, never the FQCN channels
     * @param  array<string, PassedBinding>  $passed  what a call passes the parameters whose declarations bind nothing
     *
     * @internal
     */
    public function analyzeMethod(
        string $class,
        string $method = 'toArray',
        ?string $modelClass = null,
        bool $carriesImports = true,
        array $passed = [],
    ): MethodAnalysis {
        $reflection = new ReflectionClass($class);

        if ($modelClass === null && is_a($class, JsonResource::class, true)) {
            $modelClass = resolve(ModelClassResolver::class)->resolve($reflection);
        }

        $key = 'analysis:'.$class.'@'.$method.'@'.($modelClass ?? '').($carriesImports ? '' : '@importless')
            .CallSite::signature($passed);

        $analysis = resolve(AnalysisMemo::class)->remember(
            $key,
            fn (): MethodAnalysis => $this->analyzeOnce($reflection, $method, $modelClass, $carriesImports, $key, $passed),
        );

        return clone $analysis;
    }

    /**
     * The value a method's own returns hold, read in a fresh scope over its class; null when the read declines.
     *
     * @param  class-string  $class
     * @param  array<string, PassedBinding>  $passed  what a call passes the parameters whose declarations bind nothing
     * @return ValueExpressionResult|null
     *
     * @internal
     */
    public function analyzeMethodValue(string $class, string $method, array $passed = []): ?array
    {
        $context = resolve(MethodLocator::class)->locate($class, $method);

        if ($context === null) {
            return null;
        }

        $modelClass = match (true) {
            is_a($class, Model::class, true) => $class,
            is_a($class, JsonResource::class, true) => resolve(ModelClassResolver::class)->resolve($context->reflection),
            default => null,
        };
        $scope = $this->bindingsFor($context, $modelClass, $passed);
        $analyzer = new ResourceAstAnalyzer($scope->subjectReflection, $modelClass, $method, scope: $scope, context: $context);

        return $this->returnsValue($context->method, $analyzer, $scope);
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
     * An API resource's `toArray()` comes back re-indexed as `ts:publish` publishes it.
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
        $analysis = $this->analyzeMethod($class, $method, $modelClass);

        // Laravel's filter runs over what toArray() returns, and over no other method's value.
        if ($method === 'toArray') {
            ResourceAstAnalyzer::reindexNumericKeyedArrays($analysis, new ReflectionClass($class));
        }

        return new AnalysisComposer()->compose($analysis, $fromNamespacePath);
    }

    /**
     * Build the starting scope for a located method: its subject and backing model, its body's file, its parameters,
     * bound as a helper's are, and its body's own bindings. A route-bound `Post $post` binds as its model, a
     * `?Post $post` as that model or null, and an injected `Request $request` as a request.
     *
     * @param  class-string<Model>|null  $modelClass
     * @param  array<string, PassedBinding>  $passed  what a call passes the parameters whose declarations bind nothing
     *
     * @internal
     */
    public function bindingsFor(MethodContext $context, ?string $modelClass = null, array $passed = []): AnalysisScope
    {
        $scope = new AnalysisScope($context->reflection, $modelClass);
        $methodName = $context->method->name->toString();
        $stmts = $context->method->stmts ?? [];

        if ($context->reflection->hasMethod($methodName)) {
            $method = $context->reflection->getMethod($methodName);
            $scope->declaringFileClass = LaravelTsPublish::methodDeclaringFileClass($method);

            resolve(ParameterBinder::class)->bindCall($method, $stmts, $passed, $scope);
        }

        $this->seedBody($stmts, $scope);

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
        $scope->rebase(self::genericReflection($modelClass), $modelClass);
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
     * A function-like body's own returns, merged strictly; null if it yields, may return a callable, or one is unknown.
     *
     * @return ValueExpressionResult|null
     */
    private function returnsValue(FunctionLike $function, ExpressionEngine $engine, AnalysisScope $scope): ?array
    {
        $stmts = $function->getStmts() ?? [];
        $returns = $this->collectReturnExpressions($stmts);

        // A generator returns a Generator, and a callable is the Closure object, never what calling it gives.
        if ($returns === []
            || $this->yields($stmts)
            || array_any($returns, fn (Expr $returned): bool => $this->mayHoldCallable($returned, $scope))
        ) {
            return null;
        }

        $results = array_map($engine->resolve(...), $returns);

        // unionResults() would drop a return typed unknown and claim less than PHP can return, so the read declines.
        if (array_any($results, fn (array $result): bool => $result['type'] === 'unknown')) {
            return null;
        }

        $result = ValueResult::unionResults($results);

        return $this->canEndWithoutValue($function)
            ? [...$result, 'type' => ValueResult::withNullArm($result['type'])]
            : $result;
    }

    /**
     * Run one analysis of a method body, entering its cycle guard for the duration.
     *
     * @param  ReflectionClass<object>  $reflection
     * @param  class-string<Model>|null  $modelClass
     * @param  array<string, PassedBinding>  $passed
     */
    private function analyzeOnce(
        ReflectionClass $reflection,
        string $method,
        ?string $modelClass,
        bool $carriesImports,
        string $key,
        array $passed,
    ): MethodAnalysis {
        $memo = resolve(AnalysisMemo::class);

        // Already on the stack: a self-spread or a cycle through other classes. Contribute nothing
        // rather than re-entering — the caller's own merge() treats an empty analysis as a no-op.
        if (! $memo->enter($key)) {
            return new MethodAnalysis;
        }

        try {
            $analyzer = new ResourceAstAnalyzer($reflection, $modelClass, $method, carriesImports: $carriesImports, passed: $passed);

            return $analyzer->analyze();
        } finally {
            $memo->leave($key);
        }
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
