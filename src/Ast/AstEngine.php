<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\DispatchesFqcnResults;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Public entry point: analyze any class method (or constructor) into a MethodAnalysis DTO.
 */
final class AstEngine
{
    use CollectsLocalVarBindings;
    use DispatchesFqcnResults;

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
     */
    public function analyzeMethod(string $class, string $method = 'toArray', ?string $modelClass = null): MethodAnalysis
    {
        $reflection = new ReflectionClass($class);

        if ($modelClass === null && is_a($class, JsonResource::class, true)) {
            $modelClass = resolve(ModelClassResolver::class)->resolve($reflection);
        }

        $key = $class.'@'.$method.'@'.($modelClass ?? '');

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
            $analysis = new ResourceAstAnalyzer($reflection, $modelClass, $method)->analyze();
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
     * Build the starting scope for a located method: its subject, the classes its parameters bind,
     * and the single-write local variables its body assigns.
     *
     * A route-bound `Post $post` and an injected `Request $request` are both parameter facts the
     * resource path never had, which is why they are seeded here rather than inside the analyzer.
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
                    $scope->requestVarNames[$parameter->getName()] = true;
                }
            }
        }

        $this->collectLocalVarBindings($context->method->stmts ?? [], $scope);

        return $scope;
    }

    /**
     * Analyze a class's public properties — promoted constructor params AND class-body declarations,
     * `@var` docblock first, native type second — into properties + enum/model FQCN channels.
     * Never marks a property optional: nullability is `| null`, optionality is a #[TsCasts] concern.
     *
     * @param  class-string  $class
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

            $analysis->properties[] = [
                'name' => $name,
                'type' => $result['type'],
                'optional' => false,
                'description' => '',
            ];

            $this->dispatchFqcnResults(
                $name,
                $result,
                $analysis->enumResources,
                $analysis->directEnumFqcns,
                $analysis->nestedResources,
                $analysis->modelFqcns,
                $analysis->multiEnumResourceFqcns,
            );

            foreach ($result['customImports'] ?? [] as $path => $types) {
                $analysis->customImports[$path] = [...($analysis->customImports[$path] ?? []), ...$types];
            }
        }

        return $analysis;
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
