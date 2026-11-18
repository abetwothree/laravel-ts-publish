<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Variants;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;

/**
 * A discriminant and the keys that follow its value, split into variants, with each key's row as the fold wrote it: a
 * rewrite of any of those rows after the fold leaves the variants describing rows no longer published.
 *
 * @phpstan-type RowFingerprint = array{type: string, optional: bool}
 * @phpstan-type RowFingerprints = array<string, RowFingerprint>
 *
 * @internal
 */
final readonly class VariantGroup
{
    /**
     * A group read from one literal's fold.
     *
     * @param  list<string>  $keys  the discriminant and its dependents, in item order
     * @param  RowFingerprints  $fingerprints  each key's row at the fold
     * @param  list<Variant>  $variants  in the order of each one's first value
     */
    public function __construct(
        public string $discriminant,
        public array $keys,
        public array $fingerprints,
        public array $variants,
    ) {}

    /**
     * The type and presence of each named key's row in an analysis, in the order given; a key it lacks is left out.
     *
     * @param  list<string>  $keys
     * @return RowFingerprints
     */
    public static function fingerprint(MethodAnalysis $analysis, array $keys): array
    {
        $rows = array_column($analysis->writtenProperties(), null, 'name');
        $fingerprints = [];

        foreach ($keys as $key) {
            if (isset($rows[$key])) {
                $fingerprints[$key] = ['type' => $rows[$key]['type'], 'optional' => $rows[$key]['optional']];
            }
        }

        return $fingerprints;
    }

    /**
     * Whether every key's row in the analysis is still the one the group was read beside.
     */
    public function holdsFor(MethodAnalysis $analysis): bool
    {
        return self::fingerprint($analysis, $this->keys) === $this->fingerprints;
    }
}
