<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Resources;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\DeclaringFile;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\DocblockTags;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\PropertyDocblockTypeReader;
use AbeTwoThree\LaravelTsPublish\Ast\Members\FrameworkClasses;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\DeclaredClasses;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\ResourceCollection;
use ReflectionClass;

/**
 * What an API resource's `$this->resource` is declared to hold, and the model that declaration names: a `@var` that
 * redeclares `$resource`, then the class's own `@extends JsonResource<…>`, `@mixin` and class-level
 * `@property … $resource`, then each ancestor's. ModelClassResolver takes its model; the receiver resolver its classes.
 *
 * @phpstan-type ProxySource = 'var'|'extends'|'mixin'|'property'
 * @phpstan-type ProxyArms = array{
 *     classes: non-empty-list<class-string>, named: bool, nullable: bool, source: ProxySource,
 * }
 *
 * @internal
 */
final class ProxyDeclaration
{
    /**
     * What `$resource` is declared to hold: the first declaration naming a class. A tag naming only a class nothing
     * loads, `Model` itself or the `\Eloquent` alias declares nothing, since an IDE helper writes it for the model.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $resource
     * @return ProxyArms|null
     */
    public static function of(ReflectionClass $resource): ?array
    {
        return self::declarations($resource)[0] ?? null;
    }

    /**
     * The model the declarations name: the first model arm of a tag, the class's own before an ancestor's, else of the
     * `@var`, a null arm allowed, as `@extends JsonResource<Post|null>` names `Post`.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $resource
     * @return class-string<Model>|null
     */
    public static function model(ReflectionClass $resource): ?string
    {
        $declarations = self::declarations($resource);

        // A tag outranks `@var` here, so an ancestor's `@var Model` never hides a subclass's `@mixin Post`.
        $ordered = [
            ...array_filter($declarations, fn (array $declared): bool => $declared['source'] !== 'var'),
            ...array_filter($declarations, fn (array $declared): bool => $declared['source'] === 'var'),
        ];

        foreach ($ordered as $declared) {
            $model = array_find($declared['classes'], fn (string $class): bool => is_a($class, Model::class, true));

            if ($model !== null) {
                return $model;
            }
        }

        return null;
    }

    /**
     * Every declaration of what `$resource` holds, in the order of(): read once a run per class.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $resource
     * @return list<ProxyArms>
     */
    private static function declarations(ReflectionClass $resource): array
    {
        return resolve(AnalysisMemo::class)->remember(
            'proxy-declarations:'.$resource->getName(),
            static fn (): array => self::read($resource),
        );
    }

    /**
     * Read every declaration of what `$resource` holds off the class and its ancestors.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $resource
     * @return list<ProxyArms>
     */
    private static function read(ReflectionClass $resource): array
    {
        $redeclared = self::redeclared($resource);
        $declarations = $redeclared === null ? [] : [$redeclared];

        // A collection's constructor swaps in its items, so its tags describe them, never what `$this->resource` holds.
        if ($resource->isSubclassOf(ResourceCollection::class)) {
            return $declarations;
        }

        for ($class = $resource; $class !== false; $class = $class->getParentClass()) {
            array_push($declarations, ...self::tags($class, $resource));
        }

        return $declarations;
    }

    /**
     * The class arms of a `@var` that redeclares `$resource`; null for none, or for one naming no class.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $resource
     * @return ProxyArms|null
     */
    private static function redeclared(ReflectionClass $resource): ?array
    {
        if (! $resource->hasProperty('resource')) {
            return null;
        }

        $property = $resource->getProperty('resource');

        // JsonResource declares `$resource` untyped, so a subclass can only type it in a docblock.
        $tag = FrameworkClasses::is(DeclaringFile::ofProperty($property, $resource)->getName())
            ? null
            : resolve(PropertyDocblockTypeReader::class)->tag($property, $resource);

        if ($tag === null) {
            return null;
        }

        $arms = DeclaredClasses::docblockArms(
            $tag['type'],
            $tag['context'],
            $resource->getName(),
            $property->getDeclaringClass()->getName(),
        );

        return $arms['classes'] === [] ? null : [...$arms, 'source' => 'var'];
    }

    /**
     * The declarations one class's own docblock spells, in the order of(): an `@extends` of one generic argument, a
     * `@mixin` and a `@property` naming `$resource`.
     *
     * @template C of object
     * @template T of object
     *
     * @param  ReflectionClass<C>  $class
     * @param  ReflectionClass<T>  $resource
     * @return list<ProxyArms>
     */
    private static function tags(ReflectionClass $class, ReflectionClass $resource): array
    {
        $docComment = $class->getDocComment();

        if ($docComment === false) {
            return [];
        }

        $arguments = DeclaredClasses::genericArguments(DocblockTags::type($docComment, 'extends') ?? '');
        $types = [
            'extends' => $arguments !== null && count($arguments) === 1 ? $arguments[0] : null,
            'mixin' => DocblockTags::type($docComment, 'mixin'),
            'property' => DocblockTags::named($docComment, 'property')['resource'] ?? null,
        ];
        $declarations = [];

        foreach ($types as $source => $type) {
            $arms = $type === null ? null : DeclaredClasses::docblockArms($type, $class, $resource->getName(), $class->getName());
            $classes = array_values(array_filter($arms['classes'] ?? [], fn (string $named): bool => ! self::documentsModel($named)));

            if ($arms !== null && $classes !== []) {
                $declarations[] = [...$arms, 'classes' => $classes, 'source' => $source];
            }
        }

        return $declarations;
    }

    /**
     * Whether a class a tag names is `Model` itself or the `\Eloquent` alias, which an IDE helper names for any model.
     */
    private static function documentsModel(string $class): bool
    {
        return $class === Model::class || strcasecmp($class, 'Eloquent') === 0;
    }
}
