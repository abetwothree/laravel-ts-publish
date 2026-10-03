<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers\Concerns;

use Illuminate\Support\Facades\Config;

/**
 * Shared enum FQCN/const tracking properties for transformers.
 *
 * @phpstan-type EnumPropertyFqcnInfo = array{fqcn: string, nullable: bool, isCollection?: bool}
 * @phpstan-type EnumPropertyFqcnMap = array<string, EnumPropertyFqcnInfo>
 */
trait TracksEnumImports
{
    /** @var array<string, string> FQCN => TypeScript type alias name (e.g. StatusType) */
    protected array $enumFqcnMap = [];

    /** @var array<string, string> FQCN => TypeScript const name (e.g. Status) */
    protected array $enumConstMap = [];

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
     * Return the enum property info array for this transformer.
     *
     * `isCollection` is only present on implementations that need to distinguish a single
     * enum value from an enum collection (e.g. ModelTransformer); others omit the key.
     *
     * @return EnumPropertyFqcnMap
     */
    abstract protected function enumProperties(): array;

    /**
     * Return unique FQCNs from the enum property info array, or from the part of it given.
     *
     * @param  EnumPropertyFqcnMap|null  $properties  the enum properties to read, every one when null
     * @return list<string>
     */
    protected function enumPropertyFqcns(?array $properties = null): array
    {
        return array_values(array_unique(array_column($properties ?? $this->enumProperties(), 'fqcn')));
    }
}
