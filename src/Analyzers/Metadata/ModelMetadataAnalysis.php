<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Metadata;

/**
 * Static types for one provider, restricted to what the transformer needs to validate and render a payload.
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
     */
    public function __construct(
        public array $types,
        public array $sources,
        public array $requiredKeys,
        public array $importPaths,
    ) {}

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
     * Payload keys whose type did not come from #[TsCasts] and so cannot carry an import of its own.
     *
     * @param  list<string>  $payloadKeys
     * @return list<string>
     */
    public function importFreeKeys(array $payloadKeys): array
    {
        return array_values(array_filter(
            $payloadKeys,
            fn (string $key): bool => ($this->sources[$key] ?? null) !== 'casts',
        ));
    }
}
