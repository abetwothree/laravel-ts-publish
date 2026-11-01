<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\Composition\FileImportNames;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Support\DottedTopLevelKeys;

/**
 * The top level of an Inertia page or of shared data printed as Inertia sends it: each key on the number Laravel
 * renumbers it to, and each dotted key written into its path.
 *
 * @phpstan-import-type PrintedMember from FileImportNames
 */
trait PrintsTopLevelMembers
{
    /**
     * Print each top-level prop as an object type's member, on the key Laravel sends it on, a dotted key nested.
     *
     * @param  array<array-key, array{type: string, optional: bool}>  $entries  each prop, a cast in its place, by name
     * @param  array<array-key, string>  $renumbered  the key Laravel sends each prop on, by its name
     * @return list<PrintedMember>
     */
    private function printTopLevel(array $entries, array $renumbered): array
    {
        $sent = [];
        $sources = [];

        foreach ($entries as $name => $entry) {
            $key = $renumbered[$name] ?? (string) $name;
            $sent[$key] = $entry;
            $sources[$key] = $name;
        }

        $members = [];

        foreach (DottedTopLevelKeys::nest($sent) as $key => $entry) {
            $key = (string) $key;
            $member = [
                'key' => JsEmitter::validJsObjectKey($key, allowIndexSignature: true)
                    .($entry['optional'] && ! JsEmitter::isIndexSignatureKey($key) ? '?: ' : ': '),
                'type' => $entry['type'],
                'source' => $sources[$key] ?? $key,
            ];

            if (isset($entry['pieces'])) {
                $member['pieces'] = array_map(static fn (array $piece): array => [
                    'text' => $piece['text'],
                    'source' => $piece['from'] === null ? null : $sources[$piece['from']] ?? $piece['from'],
                ], $entry['pieces']);
            }

            $members[] = $member;
        }

        return $members;
    }
}
