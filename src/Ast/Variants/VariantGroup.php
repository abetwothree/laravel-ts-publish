<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Variants;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Fold\LiteralWrites;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;

/**
 * A discriminant and the keys that follow its value, split into variants, with each key's row as the fold wrote it: a
 * rewrite of any of those rows after the fold leaves the variants describing rows no longer published.
 *
 * @phpstan-import-type ReplayedValues from LiteralWrites
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
     * The type and presence of each named key's row in an analysis, in the order given, a declared `null`'s mark read
     * as the `null` it stands for, as a strip leaves it; a key it lacks is left out.
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
                $fingerprints[$key] = ['type' => VisibleGaps::unmarked($rows[$key]['type']), 'optional' => $rows[$key]['optional']];
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

    /**
     * What each key sends in one of the group's variants, in item order: the discriminant its literal, and each
     * dependent its arm, null where the arm removes it. A numeric name keys it as an int, as PHP keys any array.
     *
     * @return ReplayedValues
     */
    public function sent(Variant $variant): array
    {
        $sent = [];

        foreach ($this->keys as $key) {
            $sent[$key] = $key === $this->discriminant
                ? ['type' => $variant->literal(), 'optional' => false]
                : $variant->dependents[$key] ?? null;
        }

        return $sent;
    }
}
