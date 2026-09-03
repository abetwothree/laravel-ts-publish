<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use PhpParser\BuilderFactory;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use ReflectionClass;
use Throwable;
use UnitEnum;

/**
 * Resolves `SomeClass::CONSTANT` value expressions and `SomeClass::class` arguments via reflection.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 */
final class ValueResolver
{
    /** Total-element cap for a class constant's array before it bails to unknown; see constantArrayWithinLimits(). */
    private const int MAX_CONSTANT_ARRAY_ELEMENTS = 200;

    /** Nesting-depth cap for a class constant's array before it bails to unknown; see constantArrayWithinLimits(). */
    private const int MAX_CONSTANT_ARRAY_DEPTH = 5;

    /**
     * Resolve a `SomeClass::class` argument node to its FQCN.
     */
    public function resolveClassConstArgument(Expr $expr): ?string
    {
        if ($expr instanceof ClassConstFetch
            && $expr->class instanceof Name
            && $expr->name instanceof Identifier
            && strtolower($expr->name->toString()) === 'class'
        ) {
            return $expr->class->toString();
        }

        return null;
    }

    /**
     * Resolve `SomeClass::CONSTANT` as a value expression. Reads the constant via reflection and
     * feeds its PHP value back through analyzeConstantValue(), reusing the engine's existing
     * scalar dispatch for leaves. Returns null for anything not a resolvable plain constant.
     *
     * @return ValueExpressionResult|null
     */
    public function resolveClassConstant(ClassConstFetch $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if (! $expr->class instanceof Name || ! $expr->name instanceof Identifier) {
            return null; // @codeCoverageIgnore
        }

        $constName = $expr->name->toString();

        // `Foo::class`/`Foo::CLASS` (the keyword is case-insensitive) is a compile-time magic
        // constant, not a real declared one — reflection can't read it. It is a string at runtime.
        if (strtolower($constName) === 'class') {
            return ['type' => 'string', 'optional' => false];
        }

        $className = $expr->class->toString();

        // Resolve self/static/parent so a constant declared on the resource (or its parent) is
        // readable, matching how analyzeNewResource()/analyzeStaticCall() treat those keywords.
        if ($className === 'self' || $className === 'static') {
            $className = $scope->subjectReflection->getName();
        } elseif ($className === 'parent') {
            $parentReflection = $scope->subjectReflection->getParentClass();

            if ($parentReflection === false) {
                return null; // @codeCoverageIgnore — every JsonResource subclass has a parent
            }

            $className = $parentReflection->getName();
        }

        if (! class_exists($className) && ! interface_exists($className) && ! enum_exists($className)) {
            return null;
        }

        $classReflection = new ReflectionClass($className);

        if (! $classReflection->hasConstant($constName)) {
            return null;
        }

        $constantReflection = $classReflection->getReflectionConstant($constName);

        // Enum cases resolve through resolveEnumFromPropertyArg()'s dedicated branch instead
        // (EnumResource::make(Status::Active) etc.) — a bare case fetch here must not be
        // reinterpreted as a plain constant's literal value.
        if ($constantReflection === false || $constantReflection->isEnumCase()) {
            return null;
        }

        try {
            $value = $constantReflection->getValue();
        } catch (Throwable) {
            // The initializer can reference another undefined constant; PHP evaluates a class
            // constant's value lazily, so that only surfaces here, not at class-load time.
            return null;
        }

        return $this->analyzeConstantValue($value, $engine);
    }

    /**
     * Convert a reflected constant's PHP value into a TS type, recursing into arrays. A scalar
     * reuses the engine's existing dispatch via a synthetic AST node instead of a parallel
     * value-to-TS mapper; a constant typed as another enum's case resolves to that enum.
     *
     * @return ValueExpressionResult|null
     */
    private function analyzeConstantValue(mixed $value, ExpressionEngine $engine): ?array
    {
        if (is_array($value)) {
            return $this->analyzeConstantArrayValue($value, $engine);
        }

        // A constant's initializer may itself be another class's enum case (`Status::Live`),
        // which getValue() hands back as the enum instance rather than a scalar.
        if ($value instanceof UnitEnum) {
            $enumFqcn = $value::class;

            return [
                'type' => LaravelTsPublish::toTsType($enumFqcn)['type'],
                'optional' => false,
                'directEnumFqcn' => $enumFqcn,
            ];
        }

        // Defensive: a class-constant expression can't construct an arbitrary object (`new` isn't
        // allowed there), so only an enum instance — handled above — reaches this as non-scalar.
        if (! is_null($value) && ! is_bool($value) && ! is_int($value) && ! is_float($value) && ! is_string($value)) {
            return null; // @codeCoverageIgnore
        }

        return $engine->resolve(new BuilderFactory()->val($value));
    }

    /**
     * Convert a reflected constant's array value into a TS shape: empty stays `never[]`, a keyed
     * array becomes an inline object, a plain list becomes an element array, and either bails to
     * unknown when the array exceeds constantArrayWithinLimits().
     *
     * @param  array<array-key, mixed>  $value
     * @return ValueExpressionResult|null
     */
    private function analyzeConstantArrayValue(array $value, ExpressionEngine $engine): ?array
    {
        if ($value === []) {
            return ['type' => 'never[]', 'optional' => false];
        }

        if (! $this->constantArrayWithinLimits($value)) {
            return null;
        }

        return array_is_list($value)
            ? $this->analyzeConstantListValue($value, $engine)
            : $this->analyzeConstantRecordValue($value, $engine);
    }

    /**
     * Convert a plain-list constant array into an element type: `T[]` when every element agrees,
     * `(A | B)[]` when they don't, or null (unknown) when any element can't itself be resolved.
     *
     * Recurses through analyzeConstantValue() — rather than delegating the whole array back to the
     * AST pipeline — so a list nested inside a keyed constant (analyzeConstantRecordValue()) is
     * resolved the same way a top-level one is: analyzeReturnArray() has no key to shape a keyless
     * item from and would otherwise silently drop every element.
     *
     * @param  list<mixed>  $value
     * @return ValueExpressionResult|null
     */
    private function analyzeConstantListValue(array $value, ExpressionEngine $engine): ?array
    {
        $types = [];
        $embeddedEnumFqcns = [];

        foreach ($value as $item) {
            $itemResult = $this->analyzeConstantValue($item, $engine);

            if ($itemResult === null || $itemResult['type'] === 'unknown') {
                return null;
            }

            $types[] = $itemResult['type'];
            $embeddedEnumFqcns = [...$embeddedEnumFqcns, ...$this->collectConstantEnumFqcns($itemResult)];
        }

        $types = array_values(array_unique($types));
        $elementType = count($types) === 1 ? $types[0] : '('.implode(' | ', $types).')';

        $result = ['type' => $elementType.'[]', 'optional' => false];

        if ($embeddedEnumFqcns !== []) {
            $result['embeddedEnumFqcns'] = array_values(array_unique($embeddedEnumFqcns));
        }

        return $result;
    }

    /**
     * Convert a keyed constant array into an inline object, formatted the same way
     * analyzeInlineArray() builds one. A member that can't itself be resolved types as `unknown`
     * rather than failing the whole shape, matching analyzeReturnArray()'s per-property behaviour.
     *
     * An int-keyed member (not routed to analyzeConstantListValue(), since the array as a whole
     * isn't a list — e.g. `[200 => 'OK', 404 => 'Not Found']`) is dropped, matching how
     * resolveKeyName() already treats a non-string AST array key everywhere else in this class.
     *
     * @param  array<array-key, mixed>  $value
     * @return ValueExpressionResult
     */
    private function analyzeConstantRecordValue(array $value, ExpressionEngine $engine): array
    {
        $parts = [];
        $embeddedEnumFqcns = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                continue;
            }

            $itemResult = $this->analyzeConstantValue($item, $engine) ?? ValueResult::unknown();
            $formattedKey = LaravelTsPublish::validJsObjectKey($key);
            $parts[] = "{$formattedKey}: {$itemResult['type']}";
            $embeddedEnumFqcns = [...$embeddedEnumFqcns, ...$this->collectConstantEnumFqcns($itemResult)];
        }

        if ($parts === []) {
            return ['type' => 'Record<string, unknown>', 'optional' => false];
        }

        $result = ['type' => '{ '.implode('; ', $parts).' }', 'optional' => false];

        if ($embeddedEnumFqcns !== []) {
            // Never deduped: each key has its own slot in the rendered object, so a repeated enum
            // is a real repeat aliasPropertyType() must walk positionally, same as an inline array.
            $result['embeddedEnumFqcns'] = $embeddedEnumFqcns;
        }

        return $result;
    }

    /**
     * Gather the enum FQCNs a resolved constant element carries — its own directEnumFqcn (a bare
     * enum-case leaf) plus any already-embedded ones (a nested list/record containing one) — so
     * analyzeConstantListValue()/analyzeConstantRecordValue() can propagate them to their caller via
     * the same embeddedEnumFqcns channel analyzeInlineArray() uses to make the import land.
     *
     * @param  ValueExpressionResult  $itemResult
     * @return list<class-string>
     */
    private function collectConstantEnumFqcns(array $itemResult): array
    {
        /** @var list<class-string> $fqcns */
        $fqcns = $itemResult['embeddedEnumFqcns'] ?? [];

        if (isset($itemResult['directEnumFqcn'])) {
            $fqcns[] = $itemResult['directEnumFqcn'];
        }

        return $fqcns;
    }

    /**
     * Guard a class-constant array against inlining an unreadable type: too many total elements
     * or nested too deep. Both limits are generous for realistic config-shaped constants (the
     * eaglesys OWNER_MINIMUM_CHANNELS shape is 2 levels deep with about a dozen elements) while
     * blocking a large external lookup table from bloating every resource that references it.
     *
     * @param  array<array-key, mixed>  $value
     */
    private function constantArrayWithinLimits(array $value): bool
    {
        if (count($value, COUNT_RECURSIVE) > self::MAX_CONSTANT_ARRAY_ELEMENTS) {
            return false;
        }

        return $this->constantArrayDepth($value) <= self::MAX_CONSTANT_ARRAY_DEPTH;
    }

    /**
     * Compute the deepest nesting level of an array, counting the array itself as depth 1.
     *
     * @param  array<array-key, mixed>  $value
     */
    private function constantArrayDepth(array $value, int $depth = 1): int
    {
        $deepest = $depth;

        foreach ($value as $item) {
            if (is_array($item)) {
                $deepest = max($deepest, $this->constantArrayDepth($item, $depth + 1));
            }
        }

        return $deepest;
    }
}
