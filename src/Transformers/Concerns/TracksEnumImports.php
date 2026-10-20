<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers\Concerns;

use Illuminate\Support\Facades\Config;

/**
 * Shared enum FQCN/const tracking properties for transformers.
 *
 * @phpstan-type EnumPropertyFqcnInfo = array{fqcn: string, nullable: bool, isCollection?: bool, alsoBare?: bool}
 * @phpstan-type EnumPropertyFqcnMap = array<array-key, EnumPropertyFqcnInfo>
 */
trait TracksEnumImports
{
    /** @var array<string, string> FQCN => TypeScript type alias name (e.g. StatusType) */
    protected array $enumFqcnMap = [];

    /** @var array<string, string> FQCN => TypeScript const name (e.g. Status) */
    protected array $enumConstMap = [];

    /**
     * Return the enum property info array for this transformer.
     *
     * `isCollection` and `alsoBare` are only present on implementations that tell a single enum value from a list
     * of it, or from a list beside it (e.g. ModelTransformer); others omit the keys.
     *
     * @return EnumPropertyFqcnMap
     */
    abstract protected function enumProperties(): array;

    /**
     * Whether the transformer should generate HasEnums value imports.
     *
     * Checks that the tolki package setting is on AND that at least one
     * enum property exists (specific array differs per transformer).
     */
    protected function shouldGenerateHasEnums(): bool
    {
        return Config::boolean('ts-publish.enums.use_tolki_package') && $this->enumProperties() !== [];
    }

    /**
     * Return unique FQCNs from the enum property info array.
     *
     * @return list<string>
     */
    protected function enumPropertyFqcns(): array
    {
        return $this->enumPropertyFqcnsOf($this->enumProperties());
    }

    /**
     * Return unique FQCNs from the given part of the enum property info array.
     *
     * @param  EnumPropertyFqcnMap  $properties
     * @return list<string>
     */
    protected function enumPropertyFqcnsOf(array $properties): array
    {
        return array_values(array_unique(array_column($properties, 'fqcn')));
    }
}
