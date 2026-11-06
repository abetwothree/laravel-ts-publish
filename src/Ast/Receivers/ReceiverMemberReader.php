<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Receivers;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\RuntimeClass;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\PropertyDocblockTypeReader;
use AbeTwoThree\LaravelTsPublish\Ast\Members\SubjectPropertyTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\ProxyDeclaration;
use AbeTwoThree\LaravelTsPublish\Ast\Values\EnumValueTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ReflectedTypeAcceptor;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrManyThrough;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;

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
    /** The methods Model::__get() reads an attribute through, any of which a subclass may override to answer more. */
    private const array ATTRIBUTE_READERS = ['__get', 'getAttribute', 'getAttributeValue'];

    /**
     * The value `<receiver>->name` sends, unioned over every class the receiver holds; null when any class cannot type
     * it. The caller adds the `| null` of a `?->` or of the receiver's short circuit.
     *
     * On a variable's join, a plain concrete model lacking the member reads it as null where another class types it.
     *
     * @param  bool  $relations  a model's relation answers a name no attribute does, as for `->name`, not for
     *                           getAttributeValue()
     * @return ValueExpressionResult|null
     */
    public static function value(ReceiverType $held, string $name, AnalysisScope $scope, bool $relations = true): ?array
    {
        $results = [];
        $readsNull = false;

        foreach ($held->classes as $heldClass) {
            // The answer, a decline included, follows the class's declarations, which reflection reads without parsing.
            DependencyRecorder::recordClass($heldClass);
            $class = $held->memberOwner($heldClass, $name, false);
            $forwarded = ProxyDeclaration::forwarded($class, $name, false);
            $result = match (true) {
                $forwarded !== null => $name === 'resource'
                    ? self::wholeValue($forwarded, $class)
                    : self::value($forwarded, $name, $scope, $relations),
                is_a($class, Model::class, true) => self::modelValue($class, $name, $scope, $held->attributeless ? $held->filled : null, $relations),
                default => EnumValueTypes::member($class, $name, $scope->carriesImports)
                    ?? self::propertyValue($class, $name, $held->declaredOnly),
            };

            if ($result === null && $held->joined && self::lacks($class, $name)) {
                $readsNull = true;

                continue;
            }

            // One untypable arm would make the union a lie; decline so dispatch reaches the floor.
            if ($result === null) {
                return null;
            }

            $results[] = $result;
        }

        if ($results === []) {
            return null;
        }

        $union = count($results) === 1 ? $results[0] : ValueResult::unionResults($results);

        return $readsNull ? [...$union, 'type' => TsTypeString::withNullArm($union['type'])] : $union;
    }

    /**
     * What `<receiver>->name` holds on one class: a model's attribute or relation, or a public property's classes.
     */
    public static function classes(string $class, string $name): ?ReceiverType
    {
        if (is_a($class, Model::class, true)) {
            return self::modelClasses($class, $name);
        }

        $forwarded = ProxyDeclaration::forwarded($class, $name, false);

        // A member of what several classes hold would need their union, which one class's read cannot give.
        if ($forwarded !== null) {
            return match (true) {
                $name === 'resource' => $forwarded,
                count($forwarded->classes) === 1 => self::classes($forwarded->memberOwner($forwarded->classes[0], $name, false), $name)?->asGuarded(),
                default => null,
            };
        }

        DependencyRecorder::recordClass($class);

        // An interface declares a property too, as PHP 8.4 lets an intersection's interface member do.
        if (! DeclaredClasses::isValueClass($class) || ! property_exists($class, $name)) {
            return null;
        }

        $subject = new ReflectionClass($class);
        $property = $subject->getProperty($name);

        // A non-public property read from outside goes to __get(), not to the declaration.
        if (! $property->isPublic() || $property->isStatic()) {
            return null;
        }

        return ReceiverType::declared(DeclaredClasses::propertyType($property, $subject, $class));
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
     * `->resource` read whole off an API resource: as any public property where the class redeclares it, else the
     * classes it holds as their published types, with the null its declaration admits; null where one has none.
     *
     * @param  class-string  $resourceClass
     * @return ValueExpressionResult|null
     */
    private static function wholeValue(ReceiverType $held, string $resourceClass): ?array
    {
        $resource = new ReflectionClass($resourceClass);
        $declared = ProxyDeclaration::of($resource);

        if ($declared !== null && $declared['source'] === 'var') {
            return self::propertyValue($resourceClass, 'resource', false);
        }

        $spelled = implode('|', array_map(fn (string $class): string => '\\'.$class, $held->classes));
        $nullable = $declared !== null && $declared['nullable'];
        $result = resolve(PropertyDocblockTypeReader::class)->readDeclared($spelled.($nullable ? '|null' : ''), $resource);

        return $result !== null && ValueResult::namesOnlyPublishedModels($result) ? $result : null;
    }

    /**
     * One model's attribute (accessor → cast → column) or, failing that, its relation; on a model holding only the
     * filled columns, a column or a relation to one that reads null adds it.
     *
     * @param  class-string<Model>  $modelFqcn
     * @param  list<string>|null  $filled  the columns a model with no attributes was written; null for any other model
     * @return ValueExpressionResult|null
     */
    private static function modelValue(string $modelFqcn, string $name, AnalysisScope $scope, ?array $filled, bool $relations): ?array
    {
        $resolver = resolve(ModelAttributeResolver::class);
        $attribute = resolve(ReflectedTypeAcceptor::class)
            ->accept($resolver->resolveAttribute($modelFqcn, $name, $scope->carriesImports, $scope->casesAsLiterals));

        if ($attribute !== null) {
            return $filled !== null && ! in_array($name, $filled, true) && self::readsNullWithoutAttributes($resolver, $modelFqcn, $name)
                ? [...$attribute, 'type' => TsTypeString::withNullArm($attribute['type'])]
                : $attribute;
        }

        $relation = $relations ? $resolver->resolveRelation($modelFqcn, $name) : null;

        if ($relation === null || $relation['type'] === 'unknown') {
            return null;
        }

        // A relation to many reads empty, which its list admits.
        $type = $filled !== null && ! str_ends_with($relation['type'], '[]') && self::findsNoRow($resolver, $modelFqcn, $name, $filled)
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
     * Whether a relation to one reads null on a model holding only the filled columns: it has no default model, and a
     * column it finds its row by is unset. A relation this cannot build reads null.
     *
     * @param  class-string<Model>  $modelFqcn
     * @param  list<string>  $filled
     */
    private static function findsNoRow(ModelAttributeResolver $resolver, string $modelFqcn, string $name, array $filled): bool
    {
        try {
            $relation = $resolver->getInstance($modelFqcn)?->{$name}();
        } catch (Throwable) {
            return true;
        }

        if (! $relation instanceof Relation) {
            return true;
        }

        $keys = LoadedRelations::parentKeys($relation)
            ?? ($relation instanceof HasOneOrManyThrough ? [$relation->getLocalKeyName()] : null);
        $default = property_exists($relation, 'withDefault') && (bool) new ReflectionProperty($relation, 'withDefault')->getValue($relation);

        return ! $default && ($keys === null || array_diff($keys, $filled) !== []);
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
     * Whether a class is a concrete model that reads a name as null: it runs Model's own attribute readers, and has no
     * attribute or `@property` it lists, and no method or property of the name, a relation's included.
     *
     * An abstract model, `Model` itself included, may be any subclass, and an override may answer any name.
     */
    private static function lacks(string $class, string $name): bool
    {
        return ReceiverType::isConcreteModel($class)
            && array_all(
                self::ATTRIBUTE_READERS,
                fn (string $method): bool => new ReflectionMethod($class, $method)->getDeclaringClass()->getName() === Model::class,
            )
            && self::listedAttributeName($class, $name) === null
            && ! method_exists($class, $name)
            && ! property_exists($class, $name)
            && resolve(ModelAttributeResolver::class)->resolveAttribute($class, $name)['type'] === 'unknown';
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
