<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Resources\Json\JsonResource;
use ReflectionClass;

/**
 * Resolves a class's override: the `#[TsType]` it declares, joined by those of its traits, interfaces and parents.
 *
 * One contributor publishes as written; two or more publish the composed `{Basename}Type` that extends them all.
 *
 * @phpstan-type Contributor = array{type: string, import: ?string, declaredBy: class-string}
 * @phpstan-type ClassOverride = array{type: string, customImports: array<string, list<string>>, composed: bool,
 *     contributors: list<Contributor>, leftOut: list<Contributor>}
 *
 * @internal
 */
final class ClassTypeOverride
{
    /** The classes another publisher writes a file for, so a composed module would take that file's name. */
    private const array OWNED = [Model::class, JsonResource::class, ShouldBroadcast::class, FormRequest::class];

    /** @var array<string, list<Contributor>> */
    private static array $contributors = [];

    /** @var array<string, ClassOverride|null> */
    private static array $resolved = [];

    /**
     * Each override a class publishes from: its own, then each trait's in `use` order, each declared interface's,
     * then its parent's by the same rule. A contributor reached twice keeps its first place, and one declaring
     * `inherit: false` brings only its own override.
     *
     * @return list<Contributor>
     */
    public static function contributors(string $class): array
    {
        $class = ltrim($class, '\\');

        if (isset(self::$contributors[$class])) {
            return self::$contributors[$class];
        }

        if (! class_exists($class) && ! interface_exists($class) && ! trait_exists($class)) {
            return [];
        }

        $reflection = new ReflectionClass($class);
        $tsType = ($reflection->getAttributes(TsType::class)[0] ?? null)?->newInstance();
        $own = $tsType === null ? [] : [self::contributor($tsType, $reflection->getName())];

        if ($tsType !== null && ! $tsType->inherit) {
            return self::$contributors[$class] = $own;
        }

        $parent = $reflection->getParentClass();
        $reached = [
            $own,
            ...array_map(self::contributors(...), $reflection->getTraitNames()),
            ...array_map(self::contributors(...), self::declaredInterfaces($reflection)),
            $parent === false ? [] : self::contributors($parent->getName()),
        ];

        $unique = [];

        foreach (array_merge(...$reached) as $contributor) {
            $unique[$contributor['declaredBy']] ??= $contributor;
        }

        return self::$contributors[$class] = array_values($unique);
    }

    /**
     * What a value of a class publishes: null when no contributor has an override. Two or more compose only when each
     * can be extended and no other publisher owns the class; otherwise the nearest wins and the rest are left out
     * with one warning.
     *
     * @return ClassOverride|null
     */
    public static function resolve(string $class): ?array
    {
        // Every call, memoized or not: an ancestor gaining or losing an override changes the answer.
        DependencyRecorder::recordClass($class);
        $class = ltrim($class, '\\');

        if (array_key_exists($class, self::$resolved)) {
            return self::$resolved[$class];
        }

        $contributors = self::contributors($class);

        return self::$resolved[$class] = match (true) {
            $contributors === [] => null,
            self::isOwned($class) => self::ownAlone($class, $contributors),
            default => self::composedOrNearest($class, $contributors),
        };
    }

    /**
     * Whether an override can join a composition: one type reference, optionally dotted or generic, never a keyword.
     */
    public static function extendable(string $type): bool
    {
        $type = trim($type);
        $open = strpos($type, '<');
        $name = $open === false ? $type : rtrim(substr($type, 0, $open));

        if ($open !== false && ! TsTypeShape::isWrapped(substr($type, $open), '<', '>')) {
            return false;
        }

        foreach (explode('.', $name) as $segment) {
            if (TsTypeString::extractImportableTypes($segment) !== [$segment]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Forget every memoized answer, at the start of a run.
     */
    public static function flush(): void
    {
        self::$contributors = [];
        self::$resolved = [];
    }

    /**
     * One override as a contributor, named by the class, trait or interface that declares it.
     *
     * @param  class-string  $declaredBy
     * @return Contributor
     */
    private static function contributor(TsType $tsType, string $declaredBy): array
    {
        return is_array($tsType->type)
            ? ['type' => $tsType->type['type'], 'import' => $tsType->type['import'] ?? null, 'declaredBy' => $declaredBy]
            : ['type' => $tsType->type, 'import' => null, 'declaredBy' => $declaredBy];
    }

    /**
     * The interfaces a class or interface adds itself: not its parent's, and not one another listed one extends.
     *
     * Reflection lists a declared interface once even when its parent implements it too, so such a repeat contributes
     * in the parent's place.
     *
     * @param  ReflectionClass<object>  $reflection
     * @return list<string>
     */
    private static function declaredInterfaces(ReflectionClass $reflection): array
    {
        $parent = $reflection->getParentClass();
        $own = array_values(array_diff($reflection->getInterfaceNames(), $parent === false ? [] : $parent->getInterfaceNames()));

        return array_values(array_filter($own, static fn (string $interface): bool => ! array_any(
            $own,
            static fn (string $other): bool => is_subclass_of($other, $interface),
        )));
    }

    /**
     * Whether another publisher writes this class's file, so its own override stands alone.
     */
    private static function isOwned(string $class): bool
    {
        return enum_exists($class) || array_any(self::OWNED, static fn (string $owned): bool => is_a($class, $owned, true));
    }

    /**
     * An owned class's own override, alone, with every inherited one left out.
     *
     * @param  non-empty-list<Contributor>  $contributors
     * @return ClassOverride|null
     */
    private static function ownAlone(string $class, array $contributors): ?array
    {
        $own = $contributors[0]['declaredBy'] === $class ? $contributors[0] : null;
        $leftOut = array_slice($contributors, $own === null ? 0 : 1);

        self::warnLeftOut($class, $own, $leftOut, 'a model, API resource, broadcast event, form request or enum publishes only its own');

        return $own === null ? null : self::asWritten($own, $leftOut);
    }

    /**
     * The composed type when every contributor can be extended, else the nearest contributor as written.
     *
     * @param  non-empty-list<Contributor>  $contributors
     * @return ClassOverride
     */
    private static function composedOrNearest(string $class, array $contributors): array
    {
        if (count($contributors) === 1) {
            return self::asWritten($contributors[0], []);
        }

        $unextendable = array_find($contributors, static fn (array $contributor): bool => ! self::extendable($contributor['type']));

        if ($unextendable === null) {
            $typeName = ComposedClassTypes::typeName($class);

            return [
                'type' => $typeName,
                'customImports' => [ComposedClassTypes::marker($class) => [$typeName]],
                'contributors' => $contributors,
                'composed' => true,
                'leftOut' => [],
            ];
        }

        $leftOut = array_slice($contributors, 1);

        self::warnLeftOut($class, $contributors[0], $leftOut, "an interface cannot extend [{$unextendable['type']}]");

        return self::asWritten($contributors[0], $leftOut);
    }

    /**
     * One contributor published as written, with the import its override names.
     *
     * @param  Contributor  $contributor
     * @param  list<Contributor>  $leftOut
     * @return ClassOverride
     */
    private static function asWritten(array $contributor, array $leftOut): array
    {
        $names = $contributor['import'] === null ? [] : TsTypeString::extractImportableTypes($contributor['type']);

        return [
            'type' => $contributor['type'],
            'customImports' => $contributor['import'] === null || $names === [] ? [] : [$contributor['import'] => $names],
            'contributors' => [$contributor],
            'composed' => false,
            'leftOut' => $leftOut,
        ];
    }

    /**
     * Tell the run which overrides a class leaves out, and why.
     *
     * @param  Contributor|null  $published
     * @param  list<Contributor>  $leftOut
     */
    private static function warnLeftOut(string $class, ?array $published, array $leftOut, string $reason): void
    {
        if ($leftOut === []) {
            return;
        }

        AnalysisWarnings::addOnce($class, sprintf(
            '%s and leaves out %s: %s.',
            $published === null ? 'Publishes no #[TsType]' : "Publishes the #[TsType] [{$published['type']}] alone",
            implode(', ', array_map(
                static fn (array $contributor): string => "[{$contributor['type']}] from [{$contributor['declaredBy']}]",
                $leftOut,
            )),
            $reason,
        ));
    }
}
