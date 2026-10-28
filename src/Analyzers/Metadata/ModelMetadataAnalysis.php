<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Metadata;

use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;

/**
 * Static types for one provider, restricted to what the transformer needs to validate and render a payload.
 *
 * @phpstan-import-type TypesImportMap from Datable
 *
 * @phpstan-type TypeSource 'inferred'|'docblock'|'casts'
 */
final readonly class ModelMetadataAnalysis
{
    /**
     * @param  array<string, string>  $types  TypeScript type per key the provider can emit
     * @param  array<string, TypeSource>  $sources  which source won each key
     * @param  array<string, true>  $requiredKeys  keys the docblock shape or #[TsCasts] declare as required
     * @param  array<string, string>  $importPaths  #[TsCasts] import path per key
     * @param  TypesImportMap  $typeImports  imports the inferred types need, relative to the companion file
     * @param  array<string, TypesImportMap>  $docblockImports  per key the docblock types, its overrides' imports
     */
    public function __construct(
        public array $types,
        public array $sources,
        public array $requiredKeys,
        public array $importPaths,
        public array $typeImports = [],
        public array $docblockImports = [],
    ) {}

    /**
     * Local names the inferred imports bring into the file (`X as Y` counts as `Y`), with those a docblock key's own
     * imports bring for that key, so one key never borrows another's.
     *
     * @return list<string>
     */
    public function importedNames(?string $key = null): array
    {
        $names = [];

        foreach ([$this->typeImports, $key === null ? [] : $this->docblockImports[$key] ?? []] as $imports) {
            foreach ($imports as $imported) {
                foreach ($imported as $name) {
                    $names[] = str_contains($name, ' as ') ? trim(substr($name, strrpos($name, ' as ') + 4)) : $name;
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Payload keys no source typed.
     *
     * @param  list<string>  $payloadKeys
     * @return list<string>
     */
    public function undeclaredKeys(array $payloadKeys): array
    {
        return array_values(array_diff($payloadKeys, array_keys($this->types)));
    }

    /**
     * Required keys the payload left out.
     *
     * @param  list<string>  $payloadKeys
     * @return list<string>
     */
    public function missingKeys(array $payloadKeys): array
    {
        return array_values(array_diff(array_keys($this->requiredKeys), $payloadKeys));
    }

    /**
     * Payload keys no #[TsCasts] entry typed, so no import path of their own covers them: only the inferred imports,
     * and a docblock key's own override imports, may bring their names.
     *
     * @param  list<string>  $payloadKeys
     * @return list<string>
     */
    public function importFreeKeys(array $payloadKeys): array
    {
        return array_values(array_filter(
            $payloadKeys,
            fn (string $key): bool => isset($this->types[$key]) && $this->sources[$key] !== 'casts',
        ));
    }
}
