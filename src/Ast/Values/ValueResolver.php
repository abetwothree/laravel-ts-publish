<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Values;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Support\PhpArrayKey;
use AbeTwoThree\LaravelTsPublish\Support\StringSerialization;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PhpParser\ConstExprEvaluationException;
use PhpParser\ConstExprEvaluator;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use ReflectionClass;
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
    use InspectsAstNodes;

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
     * Resolve `SomeClass::CONSTANT` or `SomeEnum::Case` as a value expression. Reads the constant via reflection and
     * feeds its PHP value through resolveConstantValue(), so a case types as its enum, as a constant holding one does.
     * Returns null for anything not a resolvable constant.
     *
     * @return ValueExpressionResult|null
     */
    public function resolveClassConstant(ClassConstFetch $expr, AnalysisScope $scope): ?array
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

        // A scope that carries no import cannot import the enum, and the body fallback would drop the shape holding it.
        if ($constantReflection === false || ($constantReflection->isEnumCase() && ! $scope->carriesImports)) {
            return null;
        }

        try {
            $value = $constantReflection->getValue();
        } catch (Throwable) {
            // The initializer can reference another undefined constant; PHP evaluates a class
            // constant's value lazily, so that only surfaces here, not at class-load time.
            return null;
        }

        return $this->resolveConstantValue($value);
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
     * Convert an evaluated constant value, such as a class constant's, into a TS type, recursing into arrays, or null
     * when it cannot be typed. A scalar leaf maps by its PHP type; a value that is another enum's case resolves to
     * that enum.
     *
     * @return ValueExpressionResult|null
     */
    public function resolveConstantValue(mixed $value): ?array
    {
        if (is_array($value)) {
            return $this->analyzeConstantArrayValue($value);
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

        return match (true) {
            is_null($value) => ['type' => 'null', 'optional' => false],
            is_bool($value) => ['type' => 'boolean', 'optional' => false],
            is_int($value), is_float($value) => ['type' => 'number', 'optional' => false],
            is_string($value) => ['type' => 'string', 'optional' => false],
            default => null,
        };
    }

    /**
     * The TypeScript type of a constant expression, or null for any other.
     */
    public function constantType(Expr $value, AnalysisScope $scope): ?string
    {
        try {
            return $this->resolveConstantValue($this->evaluateConstantExpression($value, $scope))['type'] ?? null;
        } catch (ConstExprEvaluationException) {
            return null;
        }
    }

    /**
     * Type an array literal json_encode() writes as a list, keyless or keyed 0 to n-1, as that list; null for an empty
     * one, one with any other keys or a by-ref item, or one spreading anything but a list. A constant one is typed as a
     * parameter default is.
     *
     * @return ValueExpressionResult|null
     */
    public function resolveListLiteral(Array_ $list, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $items = $this->listItems($list->items);

        if ($items === null) {
            return null;
        }

        try {
            $value = $this->evaluateConstantExpression($list, $scope);
            $constant = is_array($value) && array_is_list($value) ? $this->resolveConstantValue($value) : null;

            if ($constant !== null) {
                return $constant;
            }
        } catch (ConstExprEvaluationException) {
        }

        $elements = [];

        foreach ($items as $item) {
            $element = $engine->resolve($item->value);

            if ($item->unpack) {
                $elementType = $this->spreadListElementType($element['type']);

                if ($elementType === null) {
                    return null;
                }

                // A spread copies a collection's items into this array, where a resource's filter enters each one.
                unset($element['keepsKeys']);
                $element = [...$element, 'type' => $elementType];
            }

            $elements[] = $element;
        }

        // Every element is present in every response, so one no rule types cannot be left out as a union arm is.
        if (array_any($elements, fn (array $element): bool => $element['type'] === 'unknown')) {
            return ['type' => 'unknown[]', 'optional' => false];
        }

        // A missing when*() element drops its `optional`: an API resource removes it and re-indexes the rest.
        $union = ValueResult::unionResults($elements);

        return [...$union, 'type' => ValueResult::arrayWrapType($union['type']), 'optional' => false];
    }

    /**
     * Type `new X(...)` of a class that is not a resource as json_encode() writes X, or null where that is not known.
     *
     * A model declines, since its JSON depends on its runtime attributes, casts and hidden fields. So does a class
     * Inertia sends by its toArray(), toResponse() or toInertiaProperty(), none of which json_encode() calls. The value
     * is an object, which an API resource's filter never enters, so it keeps its keys.
     *
     * @return ValueExpressionResult|null
     */
    public function resolveSerializedNew(New_ $new): ?array
    {
        $result = $this->resolveStringSerializedNew($new) ?? $this->resolveClassSerializedNew($new);

        return $result === null ? null : [...$result, 'keepsKeys' => true];
    }

    /**
     * The `collect($items)` call `new Collection($items)` encodes as, or null for any other `new`.
     */
    public function collectCall(New_ $new): ?FuncCall
    {
        if (! $new->class instanceof Name || $new->class->toString() !== Collection::class || count($new->args) !== 1) {
            return null;
        }

        $arg = $new->args[0];

        // Passed by position: a named argument is `items:` here, but `value:` to collect().
        return $arg instanceof Arg && ! $arg->unpack
            ? new FuncCall(new Name('collect'), [new Arg($arg->value)], $new->getAttributes())
            : null;
    }

    /**
     * Type `new X(...)` by the string json_encode() writes X as, whatever TS name the package publishes X under:
     * `string`, or `string | null` for a nullable one; null for any other class. A date takes `toTsType()`'s type.
     *
     * @return ValueExpressionResult|null
     */
    private function resolveStringSerializedNew(New_ $new): ?array
    {
        if (! $new->class instanceof Name) {
            return null;
        }

        $class = $new->class->toString();
        $type = StringSerialization::jsonStringType($class);

        if ($type === null) {
            return null;
        }

        // So timestamps_as_date publishes a Carbon date here as `Date`, as it does everywhere else.
        return [
            'type' => is_a($class, DateTimeInterface::class, true) ? LaravelTsPublish::toTsType($class)['type'] : $type,
            'optional' => false,
        ];
    }

    /**
     * Type `new X(...)` as toTsType() reads X, or null for a class resolveSerializedNew() declines.
     *
     * @return ValueExpressionResult|null
     */
    private function resolveClassSerializedNew(New_ $new): ?array
    {
        if (! $new->class instanceof Name) {
            return null;
        }

        $class = $new->class->toString();

        if (! class_exists($class)
            || is_a($class, Model::class, true)
            || is_a($class, Arrayable::class, true)
            || is_a($class, Responsable::class, true)
            || is_a($class, 'Inertia\\ProvidesInertiaProperty', true)
        ) {
            return null;
        }

        return resolve(ReflectedTypeAcceptor::class)->accept(LaravelTsPublish::toTsType($class));
    }

    /**
     * Convert a reflected constant's array value into a TS shape: empty stays `never[]`, a keyed
     * array becomes an inline object, a plain list becomes an element array, and either bails to
     * unknown when the array exceeds constantArrayWithinLimits().
     *
     * @param  array<array-key, mixed>  $value
     * @return ValueExpressionResult|null
     */
    private function analyzeConstantArrayValue(array $value): ?array
    {
        if ($value === []) {
            return ['type' => 'never[]', 'optional' => false];
        }

        if (! $this->constantArrayWithinLimits($value)) {
            return null;
        }

        return array_is_list($value)
            ? $this->analyzeConstantListValue($value)
            : $this->analyzeConstantRecordValue($value);
    }

    /**
     * Convert a plain-list constant array into an element type: `T[]` when every element agrees,
     * `(A | B)[]` when they don't, or null (unknown) when any element can't itself be resolved.
     *
     * @param  list<mixed>  $value
     * @return ValueExpressionResult|null
     */
    private function analyzeConstantListValue(array $value): ?array
    {
        /** @var array<string, string> $types identity => type */
        $types = [];
        $embeddedEnumFqcns = [];

        foreach ($value as $item) {
            $itemResult = $this->resolveConstantValue($item);

            if ($itemResult === null || $itemResult['type'] === 'unknown') {
                return null;
            }

            // An element repeats another only when the enums behind its tokens match too: two enums share a type name.
            $itemFqcns = $this->collectConstantEnumFqcns($itemResult);
            $identity = implode("\0", [$itemResult['type'], ...$itemFqcns]);

            if (isset($types[$identity])) {
                continue;
            }

            $types[$identity] = $itemResult['type'];
            $embeddedEnumFqcns = [...$embeddedEnumFqcns, ...$itemFqcns];
        }

        // Joined first, since listType() would keep one of two elements that print alike.
        $result = ['type' => TsTypeShape::listType([implode(' | ', $types)]), 'optional' => false];

        if ($embeddedEnumFqcns !== []) {
            // One entry per token of the kept elements, in order: aliasPropertyType() walks this list positionally.
            $result['embeddedEnumFqcns'] = $embeddedEnumFqcns;
        }

        return $result;
    }

    /**
     * Convert a keyed constant array into an inline object, formatted the same way
     * analyzeInlineArray() builds one. A member that can't itself be resolved types as `unknown`
     * rather than failing the whole shape, matching analyzeReturnArray()'s per-property behaviour.
     *
     * An int key, as in `[200 => 'OK', 404 => 'Not Found']`, prints as the key `json_encode()` writes, `"200"`.
     *
     * @param  array<array-key, mixed>  $value
     * @return ValueExpressionResult
     */
    private function analyzeConstantRecordValue(array $value): array
    {
        $parts = [];
        $embeddedEnumFqcns = [];

        foreach ($value as $key => $item) {
            $itemResult = $this->resolveConstantValue($item) ?? ValueResult::unknown();
            $formattedKey = JsEmitter::validJsObjectKey((string) $key);
            $parts[] = "{$formattedKey}: {$itemResult['type']}";
            $embeddedEnumFqcns = [...$embeddedEnumFqcns, ...$this->collectConstantEnumFqcns($itemResult)];
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
     * The items of a literal json_encode() writes as a list, or null for any other literal.
     *
     * A keyless item takes the next int key, as PHP gives it, and a key written twice keeps its first position with its
     * last value. A spread's keys are unknown, so only a keyless literal may hold one.
     *
     * @param  array<ArrayItem>  $items
     * @return list<ArrayItem>|null
     */
    private function listItems(array $items): ?array
    {
        if ($items === [] || array_any($items, fn (ArrayItem $item): bool => $item->byRef)) {
            return null;
        }

        if (array_all($items, fn (ArrayItem $item): bool => $item->key === null)) {
            return array_values($items);
        }

        $stored = [];
        $next = 0;

        foreach ($items as $item) {
            $key = match (true) {
                $item->key === null => $next,
                $item->key instanceof Int_ => $item->key->value,
                $item->key instanceof String_ => PhpArrayKey::storedInt($item->key->value),
                default => null,
            };

            if ($key === null || $item->unpack) {
                return null;
            }

            $stored[$key] = $item;
            $next = max($next, $key + 1);
        }

        return array_is_list($stored) ? $stored : null;
    }

    /**
     * The element type of the list a spread adds, or null for any other type.
     *
     * A reflected `array` reads as `unknown[]` whatever its keys, so its string keys could survive the spread.
     */
    private function spreadListElementType(string $type): ?string
    {
        $element = count(TsTypeShape::splitTopLevel($type, ['|', '&'])) === 1 ? TsTypeShape::elementType($type) : null;

        return $element === 'unknown' ? null : $element;
    }

    /**
     * Guard a constant array against inlining an unreadable type: too many total elements or nested too deep. Both
     * limits are generous for a config-shaped constant, such as a nested array two levels deep with about a dozen
     * elements, while blocking a large external lookup table from bloating every resource that references it.
     *
     * @param  array<array-key, mixed>  $value
     */
    private function constantArrayWithinLimits(array $value): bool
    {
        // A flat list types as one `T[]` however long, so only an array that inlines a shape per element is capped.
        if (count($value, COUNT_RECURSIVE) > self::MAX_CONSTANT_ARRAY_ELEMENTS
            && ! (array_is_list($value) && array_all($value, fn (mixed $item): bool => ! is_array($item)))
        ) {
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
        if ($class->toLowerString() === 'parent') {
            $parentReflection = $scope->subjectReflection->getParentClass();

            return $parentReflection === false
                ? null // @codeCoverageIgnore — every JsonResource subclass has a parent
                : $parentReflection->getName();
        }

        return $this->subjectClassName($class, $scope->subjectReflection);
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
}
