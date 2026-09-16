<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use ReflectionClass;
use ReflectionProperty;

/**
 * Resolve one of a class's own properties to a value result — `@var` docblock first, native declared
 * type second, untyped default literal third — and answer whether the class declares it at all.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class SubjectPropertyTypeResolver
{
    /**
     * Framework base classes whose own property names a subclass never owns, however it redeclares them.
     *
     * This list is the extension point a new subject kind edits: add the base whose declared properties
     * that subject inherits but must not answer `$this->prop` from. `Model` earns its place here because
     * a model's own property names are the shared model engine's knowledge, which every layer reads; the
     * two resource bases are the analyzer's, and sit here only because `declaresOwnProperty()` is the one
     * caller that has to consult all three at once.
     */
    private const array FRAMEWORK_BASES = [JsonResource::class, ResourceCollection::class, Model::class];

    /**
     * Resolve a declared property's TypeScript type and FQCN channels, or null when no source
     * yields a type whose tokens can be imported.
     *
     * @param  ReflectionClass<object>  $subject
     * @return ValueExpressionResult|null
     */
    public function resolve(ReflectionClass $subject, string $name): ?array
    {
        if (! $subject->hasProperty($name)) {
            return null;
        }

        $property = $subject->getProperty($name);

        return resolve(PropertyDocblockTypeReader::class)->read($property)
            ?? resolve(ReflectedTypeAcceptor::class)->accept(LaravelTsPublish::propertyTypes($subject, $name))
            ?? $this->defaultValueType($property);
    }

    /**
     * Whether the subject declares the property itself, so PHP reads it before `__get()` forwards anywhere.
     *
     * A framework name stays excluded however the subject redeclares it: `$this->resource` holds the model,
     * and answering it from the declaration would break every read through it.
     *
     * @param  ReflectionClass<object>  $subject
     */
    public function declaresOwnProperty(ReflectionClass $subject, string $name): bool
    {
        if (! $subject->hasProperty($name)) {
            return false;
        }

        $property = $subject->getProperty($name);

        if ($property->isStatic() || str_starts_with($property->getDeclaringClass()->getName(), 'Illuminate\\')) {
            return false;
        }

        foreach (self::FRAMEWORK_BASES as $base) {
            if ($subject->isSubclassOf($base) && property_exists($base, $name)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The type of an untyped property's literal default, when every element agrees on one scalar type.
     *
     * @return ValueExpressionResult|null
     */
    private function defaultValueType(ReflectionProperty $property): ?array
    {
        if ($property->getType() !== null || ! $property->hasDefaultValue()) {
            return null;
        }

        $value = $property->getDefaultValue();
        $scalar = fn (mixed $v): ?string => match (true) {
            is_string($v) => 'string',
            is_int($v), is_float($v) => 'number',
            is_bool($v) => 'boolean',
            default => null,
        };

        if (! is_array($value)) {
            $type = $scalar($value);

            return $type === null ? null : [...ValueResult::unknown(), 'type' => $type];
        }

        $types = array_values(array_unique(array_map($scalar, $value)));

        return array_is_list($value) && count($types) === 1 && $types[0] !== null
            ? [...ValueResult::unknown(), 'type' => $types[0].'[]']
            : null;
    }
}
