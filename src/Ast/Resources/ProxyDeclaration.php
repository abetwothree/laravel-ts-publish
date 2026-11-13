<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Resources;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\DeclaringFile;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\DocblockTags;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\PropertyDocblockTypeReader;
use AbeTwoThree\LaravelTsPublish\Ast\Members\FrameworkClasses;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\DeclaredClasses;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Attributes\TsResource;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use ReflectionClass;
use ReflectionMethod;

/**
 * What an API resource's `$this->resource` is declared to hold, and the model the declarations name: `#[TsResource]`'s
 * model, a `@var` redeclaring `$resource`, then the class's own `@extends JsonResource<…>`, `@mixin` and class-level
 * `@property … $resource`, then each ancestor's. ModelClassResolver takes its model; the receiver resolver its classes.
 *
 * heldOver() joins them with the backing class, for the subject and, through held(), for another API resource.
 *
 * @phpstan-import-type Intersections from DeclaredClasses
 * @phpstan-import-type DeclaredType from DeclaredClasses
 *
 * @phpstan-type ProxySource = 'var'|'extends'|'mixin'|'property'
 * @phpstan-type ProxyArms = array{
 *     classes: non-empty-list<class-string>, named: bool, nullable: bool, intersections: Intersections,
 *     source: ProxySource,
 * }
 *
 * @internal
 */
final class ProxyDeclaration
{
    /**
     * What `$resource` is declared to hold: the first declaration naming a class. A tag naming only a class nothing
     * loads, `Model` itself or the `\Eloquent` alias declares nothing, since an IDE helper writes it for the model, and
     * beside `#[TsResource(model:)]` a tag keeps only the classes that model is, every member of an intersection.
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
     * The model the declarations name: `#[TsResource(model:)]`'s, else the first model arm of a tag, the class's own
     * before an ancestor's, else of the `@var`, a null arm allowed, as `@extends JsonResource<Post|null>` names `Post`;
     * an intersection's arm is its primary member, as `Post&HasLabel` names `Post`.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $resource
     * @return class-string<Model>|null
     */
    public static function model(ReflectionClass $resource): ?string
    {
        $explicit = self::explicitModel($resource);

        if ($explicit !== null) {
            return $explicit;
        }

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
     * What an API resource that is not a collection holds as a receiver: heldOver() over the model ModelClassResolver
     * resolves for it; null for any other class. Read once a run per class.
     *
     * @param  class-string  $resourceClass
     */
    public static function held(string $resourceClass): ?ReceiverType
    {
        if (! is_subclass_of($resourceClass, JsonResource::class) || is_subclass_of($resourceClass, ResourceCollection::class)) {
            return null;
        }

        return resolve(AnalysisMemo::class)->rememberFact('proxy-held:'.$resourceClass, static function () use ($resourceClass): ?ReceiverType {
            $resource = new ReflectionClass($resourceClass);

            return self::heldOver($resource, resolve(ModelClassResolver::class)->resolve($resource));
        });
    }

    /**
     * What `$resource` holds where no `instanceof` proof is in force: the backing class where armsOver() declares
     * nothing or every class it declares admits it, as `@var Model` admits a post, else the declared classes where each
     * arm names one; with an intersection's other members. Null where it names nothing.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $declarer  the class whose declarations say what `$resource` holds
     * @param  class-string|null  $backing  the backing model, or the class an `instanceof` guard names
     */
    public static function heldOver(ReflectionClass $declarer, ?string $backing): ?ReceiverType
    {
        $declared = self::armsOver($declarer, $backing);
        $backingNarrowsAll = $declared === null || self::admitting($declared['classes'], $backing) === $declared['classes'];

        $held = match (true) {
            $backingNarrowsAll => $backing === null ? null : ReceiverType::of($backing),
            $declared['named'] => new ReceiverType($declared['classes']),
            default => null,
        };

        $intersections = self::intersectionArms($declarer);

        return $intersections === null ? $held : $held?->withIntersections($intersections);
    }

    /**
     * What a member read off an API resource that is not a collection reads, held(): `resource`, a declared property,
     * and a member the class lacks, which Laravel's own `__get()`, or `__call()` where no macro answers, forwards.
     *
     * Null otherwise, and where a resource is held, which would forward again. The answer is guarded.
     */
    public static function forwarded(string $class, string $member, bool $isMethod): ?ReceiverType
    {
        if (! is_subclass_of($class, JsonResource::class) || is_subclass_of($class, ResourceCollection::class)) {
            return null;
        }

        // The answer follows the class's declarations, which reflection reads without parsing.
        DependencyRecorder::recordClass($class);
        $resource = new ReflectionClass($class);

        $reads = match (true) {
            $isMethod => ! $resource->hasMethod($member) && self::runsLaravels($resource, '__call') && ! $class::hasMacro($member),
            $member === 'resource' => true,
            default => ! $resource->hasProperty($member) && self::runsLaravels($resource, '__get'),
        };

        $held = $reads ? self::held($class) : null;

        return $held === null || array_any($held->classes, fn (string $holds): bool => is_a($holds, JsonResource::class, true))
            ? null
            : $held->asGuarded();
    }

    /**
     * The arms of what a class declares `$resource` to hold, an intersection of interfaces led by a member the backing
     * class implements; null for none, and for a tag that is not nullable and names one model or only classes the
     * backing class is, as `@mixin Post` or `@mixin Arrayable` over a post does: the backing class keeps its own path.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $declarer  the class whose declarations say what `$resource` holds
     * @param  class-string|null  $backing  the backing model, or the class an `instanceof` guard names
     * @return ProxyArms|null
     */
    public static function armsOver(ReflectionClass $declarer, ?string $backing): ?array
    {
        $declared = self::of($declarer);
        $declared = $declared === null ? null : [...$declared, 'classes' => self::ledByBacking($declared, $backing)];

        if ($declared === null || $declared['source'] === 'var' || $declared['nullable']) {
            return $declared;
        }

        $namesModel = (count($declared['classes']) === 1 && is_a($declared['classes'][0], Model::class, true))
            || ($backing !== null && array_all($declared['classes'], fn (string $class): bool => is_a($backing, $class, true)));

        return $namesModel ? null : $declared;
    }

    /**
     * The classes a class declares `$resource` to be, with an intersection's other members, as
     * `@extends JsonResource<Post&HasLabel>` names them; null for a declaration naming no intersection.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $declarer
     * @return DeclaredType|null
     */
    public static function intersectionArms(ReflectionClass $declarer): ?array
    {
        $declared = self::of($declarer);

        return $declared === null || $declared['intersections'] === []
            ? null
            : ['classes' => $declared['classes'], 'intersections' => $declared['intersections']];
    }

    /**
     * The declared classes a backing class is, or is a subtype of.
     *
     * @param  list<class-string>  $declared
     * @return list<class-string>
     */
    public static function admitting(array $declared, ?string $backing): array
    {
        return $backing === null
            ? []
            : array_values(array_filter($declared, fn (string $class): bool => is_a($backing, $class, true)));
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
        return resolve(AnalysisMemo::class)->rememberFact(
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

        $explicit = self::explicitModel($resource);

        for ($class = $resource; $class !== false; $class = $class->getParentClass()) {
            array_push($declarations, ...self::tags($class, $resource, $explicit));
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
     * The model `#[TsResource(model:)]` names on the class itself, when it is a model; null otherwise.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $resource
     * @return class-string<Model>|null
     */
    private static function explicitModel(ReflectionClass $resource): ?string
    {
        $model = ($resource->getAttributes(TsResource::class)[0] ?? null)?->newInstance()->model;

        return $model !== null && class_exists($model) && is_a($model, Model::class, true) ? $model : null;
    }

    /**
     * The declarations one class's own docblock spells, in the order of(): an `@extends` of one generic argument, a
     * `@mixin` and a `@property` naming `$resource`, each keeping only the arms an explicit model is every class of.
     *
     * @template C of object
     * @template T of object
     *
     * @param  ReflectionClass<C>  $class
     * @param  ReflectionClass<T>  $resource
     * @param  class-string<Model>|null  $explicit  the model `#[TsResource(model:)]` names
     * @return list<ProxyArms>
     */
    private static function tags(ReflectionClass $class, ReflectionClass $resource, ?string $explicit): array
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
            $intersections = $arms['intersections'] ?? [];
            $classes = array_values(array_filter(
                $arms['classes'] ?? [],
                fn (string $named): bool => ! self::documentsModel($named) && ($explicit === null || array_all(
                    [$named, ...$intersections[$named] ?? []],
                    fn (string $member): bool => is_a($explicit, $member, true),
                )),
            ));

            if ($arms !== null && $classes !== []) {
                $kept = array_intersect_key($intersections, array_flip($classes));
                $declarations[] = [...$arms, 'classes' => $classes, 'intersections' => $kept, 'source' => $source];
            }
        }

        return $declarations;
    }

    /**
     * The declared classes with each intersection of interfaces led by a member the backing class implements, so it
     * stands in for that member as for a class it is: `@mixin HasLabel&Arrayable` over a post leads with Arrayable.
     *
     * @param  ProxyArms  $declared
     * @return non-empty-list<class-string>
     */
    private static function ledByBacking(array $declared, ?string $backing): array
    {
        if ($backing === null || $declared['intersections'] === []) {
            return $declared['classes'];
        }

        return array_values(array_unique(array_map(
            fn (string $class): string => interface_exists($class) && ! is_a($backing, $class, true)
                ? array_find(
                    $declared['intersections'][$class] ?? [],
                    fn (string $member): bool => is_a($backing, $member, true),
                ) ?? $class
                : $class,
            $declared['classes'],
        )));
    }

    /**
     * Whether an API resource runs Laravel's own magic method by this name, which no subclass overrides.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $resource
     */
    private static function runsLaravels(ReflectionClass $resource, string $magic): bool
    {
        return FrameworkClasses::is(new ReflectionMethod($resource->getName(), $magic)->getDeclaringClass()->getName());
    }

    /**
     * Whether a class a tag names is `Model` itself or the `\Eloquent` alias, which an IDE helper names for any model.
     */
    private static function documentsModel(string $class): bool
    {
        return $class === Model::class || strcasecmp($class, 'Eloquent') === 0;
    }
}
