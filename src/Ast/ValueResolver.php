<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use BackedEnum;
use DateTimeInterface;
use JsonSerializable;
use PhpParser\BuilderFactory;
use PhpParser\ConstExprEvaluationException;
use PhpParser\ConstExprEvaluator;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;
use UnitEnum;

/**
 * Resolves `SomeClass::CONSTANT` value expressions and `SomeClass::class` arguments via reflection, and types
 * any other constant expression, such as a parameter default, by evaluating it.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
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

        $className = $this->constantClassName($expr->class, $scope);

        if ($className === null || ! $this->classLikeExists($className)) {
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
     * Evaluate a constant expression, such as a parameter default, as PHP does, reading every constant and enum
     * property it names the way evaluateFallback() describes.
     *
     * @throws ConstExprEvaluationException for a value it cannot know, such as `new`, a magic constant or a variable
     */
    public function evaluateConstantExpression(Expr $expr, AnalysisScope $scope): mixed
    {
        $evaluator = null;
        $evaluator = new ConstExprEvaluator(function (Expr $node) use ($scope, &$evaluator): mixed {
            /** @var ConstExprEvaluator $evaluator */
            return $this->evaluateFallback($node, $scope, $evaluator);
        });

        return $evaluator->evaluateSilently($expr);
    }

    /**
     * Type an evaluated constant value the way a class constant's value is typed, or null when it cannot be.
     *
     * An array whose keys all pass is_numeric() yet which is not a list declines: a resource re-indexes it into a list,
     * which the record it would type as does not describe.
     *
     * @return ValueExpressionResult|null
     */
    public function resolveConstantValue(mixed $value, ExpressionEngine $engine): ?array
    {
        return $this->holdsNumericKeyedRecord($value) ? null : $this->analyzeConstantValue($value, $engine);
    }

    /**
     * Type `new X(...)` by the string json_encode() writes X as, such as a Carbon date's, whatever TS name the package
     * publishes X under: `string`, or `string | null` for a nullable one; null for any other class.
     *
     * @return ValueExpressionResult|null
     */
    public function resolveStringSerializedNew(New_ $new): ?array
    {
        $class = $new->class instanceof Name ? $new->class->toString() : null;

        $type = $class !== null && class_exists($class) ? $this->stringSerializationType($class) : null;

        return $type === null ? null : ['type' => $type, 'optional' => false];
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
            // Never deduped: an element can itself be a record whose rendered type carries several
            // enum occurrences, so aliasPropertyType() must still walk this list positionally.
            $result['embeddedEnumFqcns'] = $embeddedEnumFqcns;
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
            $formattedKey = JsEmitter::validJsObjectKey($key);
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

    /**
     * The class a constant fetch names, or null for `parent` on a subject that has none.
     */
    private function constantClassName(Name $class, AnalysisScope $scope): ?string
    {
        $className = $class->toString();

        // Resolve self/static/parent so a constant declared on the resource (or its parent) is
        // readable, matching how analyzeNewResource()/analyzeStaticCall() treat those keywords.
        if ($className === 'self' || $className === 'static') {
            return $scope->subjectReflection->getName();
        }

        if ($className === 'parent') {
            $parentReflection = $scope->subjectReflection->getParentClass();

            return $parentReflection === false
                ? null // @codeCoverageIgnore — every JsonResource subclass has a parent
                : $parentReflection->getName();
        }

        return $className;
    }

    /**
     * Whether a class, interface or enum of this name can be loaded.
     *
     * @phpstan-assert-if-true class-string $className
     */
    private function classLikeExists(string $className): bool
    {
        return class_exists($className) || interface_exists($className) || enum_exists($className);
    }

    /**
     * ConstExprEvaluator's fallback: a class constant or enum case through reflection, a global constant as defined
     * where the types are published, or an enum case's `->name` or `->value`.
     *
     * @throws ConstExprEvaluationException for any other node
     */
    private function evaluateFallback(Expr $expr, AnalysisScope $scope, ConstExprEvaluator $evaluator): mixed
    {
        return match (true) {
            $expr instanceof ClassConstFetch => $this->evaluateClassConstFetch($expr, $scope),
            $expr instanceof ConstFetch => $this->evaluateGlobalConstant($expr),
            $expr instanceof PropertyFetch => $this->evaluateEnumProperty($expr, $evaluator),
            default => throw new ConstExprEvaluationException("Expression of type {$expr->getType()} cannot be evaluated"),
        };
    }

    /**
     * The value of the class constant or enum case a constant expression names, read through reflection since a
     * default may name a constant only its own class can see.
     *
     * @throws ConstExprEvaluationException for a computed name, or a constant that cannot be read
     */
    private function evaluateClassConstFetch(ClassConstFetch $expr, AnalysisScope $scope): mixed
    {
        if (! $expr->class instanceof Name || ! $expr->name instanceof Identifier) {
            throw new ConstExprEvaluationException('A computed class constant cannot be evaluated');
        }

        $className = $this->constantClassName($expr->class, $scope);
        $constName = $expr->name->toString();

        if ($className !== null && strtolower($constName) === 'class') {
            return $className;
        }

        $constant = $className !== null && $this->classLikeExists($className)
            ? new ReflectionClass($className)->getReflectionConstant($constName)
            : false;

        if ($constant === false) {
            throw new ConstExprEvaluationException("Constant {$className}::{$constName} cannot be read");
        }

        return $constant->getValue();
    }

    /**
     * The value of a global constant, looked up in the namespace PHP would try first, then globally.
     *
     * @throws ConstExprEvaluationException when neither is defined
     */
    private function evaluateGlobalConstant(ConstFetch $expr): mixed
    {
        $namespaced = $expr->name->getAttribute('namespacedName');

        foreach ([$namespaced instanceof Name ? $namespaced->toString() : null, $expr->name->toString()] as $name) {
            if ($name !== null && defined($name)) {
                return constant($name);
            }
        }

        throw new ConstExprEvaluationException("Constant {$expr->name->toString()} is not defined");
    }

    /**
     * The `->name` or `->value` of the enum case a constant expression reads.
     *
     * @throws ConstExprEvaluationException for any other property, or a receiver that is not an enum case
     */
    private function evaluateEnumProperty(PropertyFetch $expr, ConstExprEvaluator $evaluator): int|string
    {
        $case = $expr->name instanceof Identifier ? $evaluator->evaluateDirectly($expr->var) : null;
        $property = $expr->name instanceof Identifier ? $expr->name->toString() : '';

        return match (true) {
            $case instanceof UnitEnum && $property === 'name' => $case->name,
            $case instanceof BackedEnum && $property === 'value' => $case->value,
            default => throw new ConstExprEvaluationException("Property {$property} of an enum case cannot be read"),
        };
    }

    /**
     * The type json_encode() writes an instance as, when a string: `string | null` for a `?string` jsonSerialize(),
     * `string` for a `string` one or for a date that keeps Carbon's own, the ISO string; null for anything else.
     */
    private function stringSerializationType(string $class): ?string
    {
        if (! is_a($class, JsonSerializable::class, true)) {
            return null;
        }

        $method = new ReflectionMethod($class, 'jsonSerialize');
        $type = $method->getReturnType();

        if ($type instanceof ReflectionNamedType && $type->getName() === 'string') {
            return $type->allowsNull() ? 'string | null' : 'string';
        }

        return is_a($class, DateTimeInterface::class, true) && str_starts_with($method->getDeclaringClass()->getName(), 'Carbon\\')
            ? 'string'
            : null;
    }

    /**
     * Whether a value holds, at any depth, an array whose keys all pass is_numeric() yet which is not a list: the rule
     * a resource's removeMissingValues() re-indexes by.
     */
    private function holdsNumericKeyedRecord(mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        if (! array_is_list($value) && array_all(array_keys($value), fn (int|string $key): bool => is_numeric($key))) {
            return true;
        }

        return array_any($value, fn (mixed $item): bool => $this->holdsNumericKeyedRecord($item));
    }
}
