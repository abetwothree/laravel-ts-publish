<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallSite;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ParameterBinder;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\AnalysisComposer;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ResolvesReturnedValues;
use AbeTwoThree\LaravelTsPublish\Ast\Dispatch\ResourceExpressionHandlers;
use AbeTwoThree\LaravelTsPublish\Ast\Members\SubjectPropertyTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodContext;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\ModelClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
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
 * It and `AnalysisResult` are the engine's whole public surface. The other seven methods here are
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
     * @param  bool  $readsCall  true when the caller reads what a call to the method returns, which for a JSON:API
     *                           resource's toArray() is its body, never the resource object it publishes
     * @param  ReflectionClass<object>|null  $runtime  the caller's runtime class: what `static` names in the body,
     *                                                 and whose model `$this` reads
     * @param  bool  $assumesRuntime  the body runs on an object only assumed to be that class, as a receiver's does
     *
     * @internal
     */
    public function analyzeMethod(
        string $class,
        string $method = 'toArray',
        ?string $modelClass = null,
        bool $carriesImports = true,
        array $passed = [],
        bool $readsCall = false,
        ?ReflectionClass $runtime = null,
        bool $assumesRuntime = false,
    ): MethodAnalysis {
        $reflection = new ReflectionClass($class);

        // An ancestor's helper reads the model of the class it runs for, which the ancestor may not name.
        if ($modelClass === null && is_a(($runtime ?? $reflection)->getName(), JsonResource::class, true)) {
            $modelClass = resolve(ModelClassResolver::class)->resolve($runtime ?? $reflection);
        }

        $key = 'analysis:'.$class.'@'.$method.'@'.($modelClass ?? '').($carriesImports ? '' : '@importless')
            .($readsCall ? '@call' : '').CallSite::signature($passed).AnalysisMemo::runtimeKey($class, $runtime, $assumesRuntime);

        $analysis = resolve(AnalysisMemo::class)->remember(
            $key,
            fn (): MethodAnalysis => $this->analyzeOnce(
                $reflection,
                $method,
                $modelClass,
                $carriesImports,
                $key,
                $passed,
                $readsCall,
                $runtime,
                $assumesRuntime,
            ),
        );

        return clone $analysis;
    }

    /**
     * The value a method's own returns hold, read in a fresh scope over its class; null when the read declines.
     *
     * @param  class-string  $class
     * @param  array<string, PassedBinding>  $passed  what a call passes the parameters whose declarations bind nothing
     * @param  class-string<Model>|null  $backing  the model a caller's `instanceof` proof holds `$this->resource` to
     * @param  ReflectionClass<object>|null  $runtime  the caller's runtime class: what `static` names in the body,
     *                                                 and whose model `$this` reads
     * @param  bool  $assumesRuntime  the body runs on an object only assumed to be that class, as a receiver's does
     * @return ValueExpressionResult|null
     *
     * @internal
     */
    public function analyzeMethodValue(
        string $class,
        string $method,
        array $passed = [],
        ?string $backing = null,
        ?ReflectionClass $runtime = null,
        bool $assumesRuntime = false,
    ): ?array {
        $context = resolve(MethodLocator::class)->locate($class, $method);

        if ($context === null) {
            return null;
        }

        [$analyzer, $scope] = $this->valueReader($context, $method, $passed, $backing, $runtime, $assumesRuntime);

        return $this->returnsValue($context->method, $analyzer, $scope);
    }

    /**
     * The value one expression of a located method's body holds, read in a fresh scope over that method, as
     * analyzeMethodValue() reads the method's returns.
     *
     * @param  ReflectionClass<object>|null  $runtime  what `static` names in the body, and whose model `$this` reads
     * @param  bool  $assumesRuntime  the body runs on an object only assumed to be that class
     * @return ValueExpressionResult
     *
     * @internal
     */
    public function analyzeExpressionValue(
        MethodContext $context,
        Expr $expr,
        ?ReflectionClass $runtime = null,
        bool $assumesRuntime = false,
    ): array {
        $method = $context->method->name->toString();

        return $this->valueReader($context, $method, [], null, $runtime, $assumesRuntime)[0]->resolve($expr);
    }

    /**
     * Analyze a method and resolve its imports in one call — the whole contract a consumer needs, except for
     * a collection whose live wrap key is null or empty, whose whole answer is MethodAnalysis::$flatTypeAlias.
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
            $scope->declaringClass = $method->getDeclaringClass();

            resolve(ParameterBinder::class)->bindCall($method, $passed, $scope);
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
     * The value a closure returns when bound to a subject it was not declared in, as `Macroable::__call()` binds a
     * macro: `$this` is the subject, while its captured variables read the method that declares it; null when the
     * read declines.
     *
     * @param  class-string  $subjectClass
     * @param  class-string<Model>|null  $modelClass
     * @param  bool  $assumesRuntime  the caller only assumes the subject's class, as a receiver's body does
     * @return ValueExpressionResult|null
     *
     * @internal
     */
    public function analyzeBoundClosure(
        string $subjectClass,
        ?string $modelClass,
        ClosureExpr|ArrowFunction $closure,
        MethodContext $context,
        bool $assumesRuntime = false,
    ): ?array {
        $scope = $this->bindingsFor($context);
        $scope->rebase(self::genericReflection($subjectClass), $modelClass);
        $scope->assumesRuntime = $assumesRuntime;
        // Macroable binds a closure with the subject as its scope class, so `self` names it there.
        $scope->declaringClass = $scope->subjectReflection;
        $analyzer = new ResourceAstAnalyzer(
            $scope->subjectReflection,
            $modelClass,
            $context->method->name->toString(),
            scope: $scope,
            context: $context,
        );

        // A parameter owns its name inside the closure, and nothing binds what a call passes it.
        $scope->releaseUnclaimedParameters($closure);

        if ($closure instanceof ClosureExpr) {
            $this->seedBody($closure->stmts, $scope);
        }

        return $this->returnsValue($closure, $analyzer, $scope);
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

            // A published class's properties describe its own instances, so it is their known runtime class.
            $result = $resolver->resolve($reflection, $name, knownRuntime: $reflection) ?? ValueResult::unknown();

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
     * An analyzer reading values in a located method's body, with the scope it reads in: the method's parameters bound
     * as a call passing $passed binds them, and the model its runtime class reads.
     *
     * @param  array<string, PassedBinding>  $passed
     * @param  class-string<Model>|null  $backing
     * @param  ReflectionClass<object>|null  $runtime
     * @return array{ResourceAstAnalyzer, AnalysisScope}
     */
    private function valueReader(
        MethodContext $context,
        string $method,
        array $passed,
        ?string $backing,
        ?ReflectionClass $runtime,
        bool $assumesRuntime,
    ): array {
        $runs = $runtime?->getName() ?? $context->reflection->getName();
        $modelClass = match (true) {
            $backing !== null => $backing,
            is_a($runs, Model::class, true) => $runs,
            is_a($runs, JsonResource::class, true) => resolve(ModelClassResolver::class)->resolve($runtime ?? $context->reflection),
            default => null,
        };
        $scope = $this->bindingsFor($context, $modelClass, $passed);
        $scope->assumesRuntime = $assumesRuntime;
        $analyzer = new ResourceAstAnalyzer(
            $scope->subjectReflection,
            $modelClass,
            $method,
            scope: $scope,
            context: $context,
            runtimeClass: $runtime,
        );

        return [$analyzer, $scope];
    }

    /**
     * A function-like body's own returns, merged strictly; null if it yields, may return a callable, or one is unknown.
     * A returned variable is read through the walk over its writes where the engine reads one.
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

        $results = array_map($engine instanceof ResolvesReturnedValues
            ? fn (Expr $returned): array => $engine->resolveReturned($returned, $stmts)
            : $engine->resolve(...), $returns);

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
     * @param  ReflectionClass<object>|null  $runtime
     */
    private function analyzeOnce(
        ReflectionClass $reflection,
        string $method,
        ?string $modelClass,
        bool $carriesImports,
        string $key,
        array $passed,
        bool $readsCall,
        ?ReflectionClass $runtime,
        bool $assumesRuntime,
    ): MethodAnalysis {
        $memo = resolve(AnalysisMemo::class);

        // Already on the stack: a self-spread or a cycle through other classes. Contribute nothing
        // rather than re-entering — the caller's own merge() treats an empty analysis as a no-op.
        if (! $memo->enter($key)) {
            return new MethodAnalysis;
        }

        try {
            $analyzer = new ResourceAstAnalyzer(
                $reflection,
                $modelClass,
                $method,
                carriesImports: $carriesImports,
                passed: $passed,
                runtimeClass: $runtime,
                readsCall: $readsCall,
                assumesRuntime: $assumesRuntime,
            );

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
