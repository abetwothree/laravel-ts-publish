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
use Illuminate\Support\Str;

class RelationMap
{
    /** @var array<class-string, string>|null */
    protected static ?array $map = null;

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
            array_filter(config()->array('ts-publish.models.relation_nullability_map', []), 'is_string'),
        );

        return self::$map = $merged;
    }

    /**
     * Resolve the nullability strategy for a relation type.
     *
     * Accepts a short class name (e.g. 'HasOne' from ModelInspector) or a FQCN.
     */
    public function strategyFor(string $type): string
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

        return 'nullable';
    }
}
