<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * Assigns collision-free local TypeScript names to imported FQCNs.
 *
 * resolve() returns a deterministic FQCN => local-name map, extending each alias by namespace
 * segment until unique (numeric suffix as final tiebreak).
 *
 * @phpstan-type RegistryEntry array{fqcn: string, typeName: string, preferredAlias: string|null}
 */
class ImportNameRegistry
{
    /** @var array<string, RegistryEntry> keyed by FQCN */
    protected array $entries = [];

    /** @var array<string, true> */
    protected array $reserved = [];

    /**
     * @param  list<string>  $skipSegments  Namespace segments skipped when deriving prefixes.
     */
    public function __construct(
        protected array $skipSegments = ['Models', 'Enums', 'App'],
    ) {}

    /**
     * Reserve local names the generated file already uses, such as its own interface or another registry's imports.
     */
    public function reserve(string ...$localNames): void
    {
        foreach ($localNames as $localName) {
            $this->reserved[$localName] = true;
        }
    }

    /**
     * Register an FQCN to import under $typeName, optionally suggesting an alias.
     */
    public function register(string $fqcn, string $typeName, ?string $preferredAlias = null): void
    {
        $this->entries[$fqcn] = [
            'fqcn' => $fqcn,
            'typeName' => $typeName,
            'preferredAlias' => $preferredAlias,
        ];
    }

    /**
     * Resolve every registered FQCN to a unique, deterministic local name.
     *
     * @return array<string, string> FQCN => local name
     */
    public function resolve(): array
    {
        /** @var array<string, list<RegistryEntry>> $byName */
        $byName = [];

        foreach ($this->entries as $entry) {
            $byName[$entry['typeName']][] = $entry;
        }

        // Group-processing order must depend only on the registered names, not on the
        // order they were registered in, so cross-group collisions resolve the same way.
        ksort($byName);

        /** @var array<string, string> $resolved */
        $resolved = [];
        /** @var array<string, true> $taken */
        $taken = $this->reserved;

        // Names that stay unaliased claim their slot first.
        foreach ($byName as $typeName => $group) {
            if (count($group) === 1 && ! isset($this->reserved[$typeName])) {
                $resolved[$group[0]['fqcn']] = $typeName;
                $taken[$typeName] = true;
            }
        }

        foreach ($byName as $typeName => $group) {
            if (count($group) === 1 && ! isset($this->reserved[$typeName])) {
                continue;
            }

            foreach ($this->assignGroupAliases($group, $taken) as $fqcn => $alias) {
                $resolved[$fqcn] = $alias;
            }
        }

        // Preserve registration order in the output for stable diffs.
        $ordered = [];
        foreach ($this->entries as $fqcn => $entry) {
            $ordered[$fqcn] = $resolved[$fqcn];
        }

        return $ordered;
    }

    /**
     * Assign aliases to one colliding group. Each round advances every member whose own
     * candidate still collides, so none is compared against a stale one; already-unique
     * members keep theirs. FQCN-sorted iteration makes numeric tiebreaks deterministic.
     *
     * @param  list<RegistryEntry>  $group
     * @param  array<string, true>  $taken
     * @return array<string, string> FQCN => alias
     */
    protected function assignGroupAliases(array $group, array &$taken): array
    {
        usort($group, fn (array $a, array $b): int => strcmp($a['fqcn'], $b['fqcn']));

        /** @var array<string, array{entry: RegistryEntry, candidate: string, depth: int, exhausted: bool}> $states */
        $states = [];

        foreach ($group as $entry) {
            $segments = $this->prefixSegments($entry['fqcn']);
            $states[$entry['fqcn']] = [
                'entry' => $entry,
                'candidate' => $entry['preferredAlias'] ?? ($segments[count($segments) - 1] ?? '').$entry['typeName'],
                'depth' => $entry['preferredAlias'] !== null ? 0 : 1,
                'exhausted' => false,
            ];
        }

        $segmentCounts = array_values(array_map(
            fn (array $s): int => count($this->prefixSegments($s['entry']['fqcn'])),
            $states,
        ));

        $maxRounds = 1 + max(1, ...$segmentCounts);

        for ($round = 0; $round <= $maxRounds; $round++) {
            $counts = array_count_values(array_column($states, 'candidate'));
            $advanced = false;

            foreach ($states as $fqcn => $state) {
                $collides = $counts[$state['candidate']] > 1 || isset($taken[$state['candidate']]);

                if (! $collides || $state['exhausted']) {
                    continue;
                }

                $segments = $this->prefixSegments($fqcn);
                $depth = max(1, $state['depth'] + 1); // preferred alias drops to depth 1

                if ($depth > count($segments)) {
                    $states[$fqcn]['exhausted'] = true;

                    continue;
                }

                $prefix = implode('', array_slice($segments, -$depth));
                $states[$fqcn]['candidate'] = $prefix.$state['entry']['typeName'];
                $states[$fqcn]['depth'] = $depth;
                $advanced = true;
            }

            if (! $advanced) {
                break;
            }
        }

        // Numeric tiebreak for members that exhausted their namespace (or still collide).
        /** @var array<string, string> $result */
        $result = [];

        foreach ($states as $fqcn => $state) {
            $candidate = $state['candidate'];

            for ($i = 2; isset($taken[$candidate]); $i++) {
                $candidate = $state['candidate'].$i;
            }

            $taken[$candidate] = true;
            $result[$fqcn] = $candidate;
        }

        return $result;
    }

    /**
     * StudlyCase namespace segments usable as alias prefixes, nearest-last.
     *
     * @return list<string>
     */
    protected function prefixSegments(string $fqcn): array
    {
        $namespace = Str::contains($fqcn, '\\') ? Str::beforeLast($fqcn, '\\') : '';

        $strip = Config::string('ts-publish.namespace_strip_prefix', '');

        if ($strip !== '' && str_starts_with($namespace, $strip)) {
            $namespace = ltrim(substr($namespace, strlen($strip)), '\\');
        }

        $all = array_values(array_filter(explode('\\', $namespace), fn (string $s): bool => $s !== ''));

        $segments = array_values(array_filter(
            $all,
            fn (string $s): bool => ! in_array($s, $this->skipSegments, true),
        ));

        // Every segment skipped (e.g. App\Models) — fall back to the raw segments so a
        // reserved-name collision can still alias (App\Models\Order => ModelsOrder).
        if ($segments === []) {
            $segments = $all;
        }

        return array_map(fn (string $s): string => Str::studly($s), $segments);
    }
}
