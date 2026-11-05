<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Receivers;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\RuntimeClass;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Members\SubjectPropertyTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Values\EnumValueTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ReflectedTypeAcceptor;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * One member read off a class a receiver holds, as PHP and Model::__get() read it: a model's attribute (accessor, cast,
 * column) else its relation, an enum's member, or a public instance property; as the value it sends or the classes it
 * holds. The rules are in docs/components/receiver-types.md § Property access on a receiver.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class ReceiverMemberReader
{
    /**
     * The value `<receiver>->name` sends, unioned over every class the receiver holds; null when any class cannot type
     * it. The caller adds the `| null` of a `?->` or of the receiver's short circuit.
     *
     * @param  bool  $relations  a model's relation answers a name no attribute does, as for `->name`, not for
     *                           getAttributeValue()
     * @return ValueExpressionResult|null
     */
    public static function value(ReceiverType $held, string $name, AnalysisScope $scope, bool $relations = true): ?array
    {
        $attributeless = $held->attributeless && ! in_array($name, $held->filled, true);
        $results = [];

        foreach ($held->classes as $class) {
            // The answer, a decline included, follows the class's declarations, which reflection reads without parsing.
            DependencyRecorder::recordClass($class);
            $result = is_a($class, Model::class, true)
                ? self::modelValue($class, $name, $scope, $attributeless, $relations)
                : EnumValueTypes::member($class, $name, $scope->carriesImports)
                    ?? self::propertyValue($class, $name, $held->declaredOnly);

            // One untypable arm would make the union a lie; decline so dispatch reaches the floor.
            if ($result === null) {
                return null;
            }

            $results[] = $result;
        }

        return count($results) === 1
            ? $results[0]
            : ValueResult::unionResults($results);
    }

    /**
     * What `<receiver>->name` holds on one class: a model's attribute or relation, or a public property's classes.
     */
    public static function classes(string $class, string $name): ?ReceiverType
    {
        if (is_a($class, Model::class, true)) {
            return self::modelClasses($class, $name);
        }

        DependencyRecorder::recordClass($class);

        if (! class_exists($class) || ! property_exists($class, $name)) {
            return null;
        }

        $subject = new ReflectionClass($class);
        $property = $subject->getProperty($name);

        // A non-public property read from outside goes to __get(), not to the declaration.
        if (! $property->isPublic() || $property->isStatic()) {
            return null;
        }

        $classes = DeclaredClasses::propertyClasses($property, $subject, $class);

        return $classes === null ? null : new ReceiverType($classes);
    }

    /**
     * What a model relation holds once loaded: its related model, a collection of them, or any of a morphTo's targets.
     *
     * @param  class-string<Model>  $model
     */
    public static function relationClasses(string $model, string $name): ?ReceiverType
    {
        $resolver = resolve(ModelAttributeResolver::class);
        $relation = $resolver->resolveRelation($model, $name);

        if ($relation['morphFqcns'] !== []) {
            return new ReceiverType($relation['morphFqcns']);
        }

        if ($relation['modelFqcn'] !== null) {
            return str_ends_with($relation['type'], '[]')
                ? new ReceiverType([EloquentCollection::class], elementModel: $relation['modelFqcn'])
                : ReceiverType::of($relation['modelFqcn']);
        }

        // Only a morphTo is a known relation that names no related model.
        $isMorphTo = $resolver->getRelations($model)?->firstWhere('name', $name) !== null;

        return $isMorphTo ? ReceiverType::of($resolver->resolveMorphToBound($model, $name)) : null;
    }

    /**
     * One model's attribute (accessor → cast → column) or, failing that, its relation; a column or a relation to one a
     * model with no attributes reads is null.
     *
     * @param  class-string<Model>  $modelFqcn
     * @return ValueExpressionResult|null
     */
    private static function modelValue(string $modelFqcn, string $name, AnalysisScope $scope, bool $attributeless, bool $relations): ?array
    {
        $resolver = resolve(ModelAttributeResolver::class);
        $attribute = resolve(ReflectedTypeAcceptor::class)
            ->accept($resolver->resolveAttribute($modelFqcn, $name, $scope->carriesImports, $scope->casesAsLiterals));

        if ($attribute !== null) {
            return $attributeless && self::readsNullWithoutAttributes($resolver, $modelFqcn, $name)
                ? [...$attribute, 'type' => TsTypeString::withNullArm($attribute['type'])]
                : $attribute;
        }

        $relation = $relations ? $resolver->resolveRelation($modelFqcn, $name) : null;

        if ($relation === null || $relation['type'] === 'unknown') {
            return null;
        }

        // With no key to find it by, Laravel reads a relation to one as null and a relation to many as empty.
        $type = $attributeless && ! str_ends_with($relation['type'], '[]')
            ? TsTypeString::withNullArm($relation['type'])
            : $relation['type'];

        /** @var ValueExpressionResult $result */
        $result = [...ValueResult::unknown(), 'type' => $type];

        if ($relation['modelFqcn'] !== null) {
            $result['modelFqcn'] = $relation['modelFqcn'];
        }

        if ($relation['morphFqcns'] !== []) {
            $result['embeddedModelFqcns'] = $relation['morphFqcns'];
        }

        return $result;
    }

    /**
     * Whether a model with no attributes reads a column as null: one its new instance holds no default for, which no
     * accessor or cast class computes; a built-in or enum cast reads a missing value as null, a cast class may not.
     *
     * @param  class-string<Model>  $modelFqcn
     */
    private static function readsNullWithoutAttributes(ModelAttributeResolver $resolver, string $modelFqcn, string $name): bool
    {
        $column = $resolver->getAttributes($modelFqcn)?->firstWhere('name', $name);
        $instance = $resolver->getInstance($modelFqcn);
        $cast = $column['cast'] ?? null;
        $castClass = $cast === null ? null : explode(':', $cast, 2)[0];

        return $column !== null
            && $column['type'] !== null
            && $instance !== null
            && ! array_key_exists($name, $instance->getAttributes())
            && ! in_array($cast, ['accessor', 'attribute'], true)
            && ($castClass === null || ! class_exists($castClass) || enum_exists($castClass));
    }

    /**
     * One non-model class's public instance property, by the rules that type a method's return on the same receiver,
     * a receiver spelled `self`, `static` or `parent` reading one a subclass can redeclare by its declaration alone.
     *
     * @param  class-string  $class
     * @return ValueExpressionResult|null
     */
    private static function propertyValue(string $class, string $name, bool $declaredOnly): ?array
    {
        $reflection = new ReflectionClass($class);
        $property = $reflection->hasProperty($name) ? $reflection->getProperty($name) : null;

        // An outside read of a non-public or static property goes to __get(), never to the declaration.
        if ($property === null || ! $property->isPublic() || $property->isStatic()) {
            return null;
        }

        $properties = resolve(SubjectPropertyTypeResolver::class);
        $result = $declaredOnly && ReceiverType::overridable($reflection, $property)
            ? $properties->declaredType($reflection, $property)
            : $properties->resolve($reflection, $name, RuntimeClass::assumed($reflection));

        return $result !== null && ValueResult::namesOnlyPublishedModels($result) ? $result : null;
    }

    /**
     * What a model member read holds: an attribute the model lists, else its relation, the order Model::__get() reads.
     *
     * @param  class-string<Model>  $model
     */
    private static function modelClasses(string $model, string $name): ?ReceiverType
    {
        $attribute = self::listedAttributeName($model, $name);

        // A name only an `@property` tag types holds what a dynamic or a set relation puts there.
        if ($attribute === null) {
            return self::relationClasses($model, $name) ?? self::typedModel($model, $name);
        }

        $attributeClass = resolve(ModelAttributeResolver::class)->resolveAttributeClass($model, $attribute);

        // The attribute hides a relation of the same name, so one holding no class names none.
        return $attributeClass === null ? self::typedModel($model, $attribute) : ReceiverType::of($attributeClass);
    }

    /**
     * The one model a member's type names where no declaration does, as an untyped getter or an `@property` tag can.
     *
     * @param  class-string<Model>  $model
     */
    private static function typedModel(string $model, string $name): ?ReceiverType
    {
        $type = resolve(ModelAttributeResolver::class)->resolveAttribute($model, $name);
        $held = $type['classFqcns'];

        // A list of the model, or a union of it with another type, holds no single model.
        return count($held) === 1 && is_a($held[0], Model::class, true) && preg_match('/^\w+$/', TsTypeString::withoutNullArm($type['type'])) === 1
            ? ReceiverType::of($held[0])
            : null;
    }

    /**
     * The name the model lists the attribute Model::__get() reads under, or null when it lists none.
     *
     * @param  class-string<Model>  $model
     */
    private static function listedAttributeName(string $model, string $name): ?string
    {
        $attributes = resolve(ModelAttributeResolver::class)->getAttributes($model);

        if ($attributes?->firstWhere('name', $name) !== null) {
            return $name;
        }

        // Eloquent camel-cases a key only to find an accessor method, never to read a column.
        $snake = Str::snake($name);
        $accessor = $attributes?->firstWhere('name', $snake);

        return in_array($accessor['cast'] ?? null, ['attribute', 'accessor'], true) ? $snake : null;
    }
}
