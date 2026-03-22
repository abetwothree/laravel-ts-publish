<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers\Concerns;

/**
 * Shared enum FQCN/const tracking properties for transformers.
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
        return config()->boolean('ts-publish.enums_use_tolki_package')
            && $this->enumProperties() !== [];
    }

    /**
     * Return the enum property info array for this transformer.
     *
     * @return array<string, array{fqcn: string, nullable: bool}>
     */
    abstract protected function enumProperties(): array;

    /**
     * Return unique FQCNs from the enum property info array.
     *
     * @return list<string>
     */
    protected function enumPropertyFqcns(): array
    {
        return array_values(array_unique(array_column($this->enumProperties(), 'fqcn')));
    }
}
