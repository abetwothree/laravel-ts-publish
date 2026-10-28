<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use ReflectionMethod;

class RelationMap
{
    /** The relations that load a collection, never one model or null. */
    private const array TO_MANY = [HasMany::class, HasManyThrough::class, BelongsToMany::class, MorphMany::class];

    /** @var array<class-string, string>|null */
    protected static ?array $map = null;

    /**
     * Whether a relation loads a collection: its short type names "many" and no `morphTo`, or its class extends one of
     * Laravel's to-many relations and keeps either the initRelation() that starts each eager load with an empty
     * collection, or the getResults() and match() that load the collection.
     */
    public static function isToMany(string $type, ?string $relationClass = null): bool
    {
        if (str_contains(strtolower($type), 'many') && ! str_ends_with($type, 'MorphTo')) {
            return true;
        }

        if ($relationClass === null) {
            return false;
        }

        foreach (self::TO_MANY as $toMany) {
            if (is_a($relationClass, $toMany, true)) {
                // A relation that loads one model must replace initRelation() too, or an eager load leaves `[]`.
                return self::keepsMethodOf($relationClass, 'initRelation', $toMany)
                    || (self::keepsMethodOf($relationClass, 'getResults', $toMany)
                        && self::keepsMethodOf($relationClass, 'match', $toMany));
            }
        }

        return false;
    }

    /**
     * Forget the cached map, so the next gather() reads `relation_nullability_map` again.
     */
    public static function reset(): void
    {
        self::$map = null;
    }

    /**
     * @return array<class-string, string>
     */
    public function gather(): array
    {
        if (self::$map !== null) {
            return self::$map;
        }

        $map = [
            // Always nullable — the related record may not exist
            HasOne::class => 'nullable',
            MorphOne::class => 'nullable',
            HasOneThrough::class => 'nullable',

            // Check the foreign key column's DB-level nullability
            BelongsTo::class => 'fk',

            // Check both morph type and morph id column nullability
            MorphTo::class => 'morph',

            // Collection relations — never nullable (empty array, not null)
            HasMany::class => 'never',
            HasManyThrough::class => 'never',
            BelongsToMany::class => 'never',
            MorphMany::class => 'never',
            MorphToMany::class => 'never',
        ];

        /** @var array<class-string, string> $merged */
        $merged = array_merge(
            $map,
            array_filter(Config::array('ts-publish.models.relation_nullability_map', []), 'is_string'),
        );

        return self::$map = $merged;
    }

    /**
     * Resolve the nullability strategy for a relation type.
     *
     * Accepts a short class name (e.g. 'HasOne' from ModelInspector) or a FQCN. A relation class the map does not
     * name takes the strategy of its nearest parent the map names, unless it loads one model and that parent a list.
     */
    public function strategyFor(string $type, ?string $relationClass = null): string
    {
        $map = $this->gather();

        // Direct match (already a FQCN or exact key)
        if (isset($map[$type])) {
            return $map[$type];
        }

        // Try the standard Laravel relations namespace
        $fqcn = 'Illuminate\\Database\\Eloquent\\Relations\\'.$type;
        if (isset($map[$fqcn])) {
            return $map[$fqcn];
        }

        // Fall back to matching any key ending with the short name
        $shortName = Str::afterLast($type, '\\');
        foreach ($map as $key => $strategy) {
            if (Str::afterLast($key, '\\') === $shortName) {
                return $strategy;
            }
        }

        $loadsOne = $relationClass !== null && ! self::isToMany($type, $relationClass);
        $parents = $relationClass === null ? false : class_parents($relationClass);

        foreach ($parents ?: [] as $parent) {
            if (isset($map[$parent]) && ! ($loadsOne && self::isToMany(Str::afterLast($parent, '\\'), $parent))) {
                return $map[$parent];
            }
        }

        return 'nullable';
    }

    /**
     * Whether a class runs the given method as the ancestor declares it, or as a class above that ancestor does.
     */
    private static function keepsMethodOf(string $class, string $method, string $ancestor): bool
    {
        return is_a($ancestor, new ReflectionMethod($class, $method)->class, true);
    }
}
