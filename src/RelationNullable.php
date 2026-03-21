<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish;

use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

/**
 * Determines whether a singular Eloquent relation should be typed as nullable,
 * using the configured RelationMap strategies and DB-level column inspection.
 *
 * @phpstan-type RelationEntry = array{name: string, type: string, related: class-string<Model>}
 * @phpstan-type AttributeEntry = array{name: string, type: string|null, cast: string|null, nullable: bool}
 */
class RelationNullable
{
    /**
     * @param  Collection<int, AttributeEntry>|null  $attributes
     */
    public function __construct(
        protected ?Model $modelInstance = null,
        protected ?Collection $attributes = null,
    ) {}

    /**
     * @param  RelationEntry  $relation
     */
    public function isNullable(array $relation): bool
    {
        $strategy = LaravelTsPublish::relationStrategy($relation['type']);

        return match ($strategy) {
            'never' => false,
            'nullable' => true,
            'fk' => $this->isForeignKeyNullable($relation),
            'morph' => $this->isMorphNullable($relation),
            default => true,
        };
    }

    /**
     * @param  RelationEntry  $relation
     */
    protected function isForeignKeyNullable(array $relation): bool
    {
        $relationInstance = $this->modelInstance?->{$relation['name']}();

        if (! $relationInstance instanceof BelongsTo) {
            return true;
        }

        /** @var string|list<string> $fkName */
        $fkName = $relationInstance->getForeignKeyName();

        if (is_array($fkName)) {
            foreach ($fkName as $column) {
                if ($this->isAttributeNullable($column)) {
                    return true;
                }
            }

            return false;
        }

        return $this->isAttributeNullable($fkName);
    }

    /**
     * @param  RelationEntry  $relation
     */
    protected function isMorphNullable(array $relation): bool
    {
        $relationInstance = $this->modelInstance?->{$relation['name']}();

        if (! $relationInstance instanceof MorphTo) {
            return true;
        }

        /** @var string|list<string> $fkName */
        $fkName = $relationInstance->getForeignKeyName();
        $morphType = $relationInstance->getMorphType();

        if (is_array($fkName)) {
            foreach ($fkName as $column) {
                if ($this->isAttributeNullable($column)) {
                    return true;
                }
            }

            return $this->isAttributeNullable($morphType);
        }

        return $this->isAttributeNullable($fkName) || $this->isAttributeNullable($morphType);
    }

    protected function isAttributeNullable(string $columnName): bool
    {
        $attribute = $this->attributes?->first(fn (array $attr) => $attr['name'] === $columnName);

        return $attribute !== null ? $attribute['nullable'] : true;
    }
}
