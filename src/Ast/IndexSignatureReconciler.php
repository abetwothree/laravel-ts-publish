<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;

/**
 * Settles each template-literal index signature against the keys published beside it.
 *
 * TypeScript checks a signature against every key and signature its pattern covers (TS2411, TS2413), so a value the
 * body did not give it — a docblock fill or a same-pattern union — is kept only where no such check can fail.
 *
 * @internal
 */
final class IndexSignatureReconciler
{
    /**
     * Union each signature with the same-pattern keys beside it, or, where one of them cannot join the union or
     * another signature's pattern may overlap its own, put back the value its body alone gives it.
     */
    public function reconcile(MethodAnalysis $analysis): void
    {
        $signatures = [];
        $published = [];

        foreach ($analysis->properties as $index => $property) {
            if (JsEmitter::isIndexSignatureKey($property['name'])) {
                $signatures[$property['name']][] = $index;
            } else {
                // A later write to a named key replaces the earlier one, so only the last entry is ever published.
                $published[$property['name']] = $property['type'];
            }
        }

        $dropped = [];

        foreach ($signatures as $name => $indexes) {
            $pattern = $this->keyPattern($name);

            if ($pattern === null) {
                continue;
            }

            $keys = [$name];
            $types = [];

            foreach ($indexes as $index) {
                $types[] = $analysis->properties[$index]['type'];
            }

            foreach ($published as $key => $type) {
                if (preg_match($pattern, (string) $key) === 1) {
                    $keys[] = (string) $key;
                    $types[] = $type;
                }
            }

            $arms = $this->overlapsAnother($name, array_keys($signatures))
                ? null
                : $this->unionArms($analysis, $keys, $types);

            if ($arms === null) {
                $this->restoreBodyTypes($analysis, $indexes);
            } elseif (count($types) > 1) {
                $dropped += $this->union($analysis, $indexes, $arms);
            }
        }

        if ($dropped !== []) {
            $analysis->properties = array_values(array_diff_key($analysis->properties, $dropped));
        }
    }

    /**
     * Collapse a signature's entries into the first, typed with every arm, keeping the value a name-keyed publish
     * of the body types would give it so an outer conflict can still put that back.
     *
     * @param  non-empty-list<int>  $indexes
     * @param  list<string>  $arms
     * @return array<int, true> the later entries, now folded into the first
     */
    private function union(MethodAnalysis $analysis, array $indexes, array $arms): array
    {
        $last = $analysis->properties[$indexes[count($indexes) - 1]];
        $bodyType = $last['bodyType'] ?? $last['type'];
        $entry = $analysis->properties[$indexes[0]];

        $entry['type'] = TsTypeString::orUndefined(TsTypeString::hoistNull($arms));
        unset($entry['bodyType']);

        if ($entry['type'] !== $bodyType) {
            $entry['bodyType'] = $bodyType;
        }

        $analysis->properties[$indexes[0]] = $entry;

        return array_fill_keys(array_slice($indexes, 1), true);
    }

    /**
     * Every arm of the given types except `undefined`, or null when one of them cannot join: an `unknown` arm
     * swallows the rest, and a class token or FQCN channel is imported and rewritten under its own key's name,
     * so a copy in the signature would miss both.
     *
     * @param  list<string>  $keys  the signature's name and the named keys its pattern matches
     * @param  list<string>  $types  the signature's entries' types and those keys' types
     * @return list<string>|null
     */
    private function unionArms(MethodAnalysis $analysis, array $keys, array $types): ?array
    {
        foreach ($keys as $key) {
            if ($analysis->hasFqcnChannel($key)) {
                return null;
            }
        }

        $arms = [];

        foreach ($types as $type) {
            if (TsTypeString::shapeValueHasUnimportableToken($type)) {
                return null;
            }

            foreach (TsTypeString::splitTopLevelUnion($type) as $arm) {
                if ($arm === 'unknown') {
                    return null;
                }

                if ($arm !== 'undefined') {
                    $arms[] = $arm;
                }
            }
        }

        return $arms;
    }

    /**
     * Put back the value each entry's body gives it, on the entries a docblock fill or union changed.
     *
     * @param  list<int>  $indexes
     */
    private function restoreBodyTypes(MethodAnalysis $analysis, array $indexes): void
    {
        foreach ($indexes as $index) {
            if (isset($analysis->properties[$index]['bodyType'])) {
                $analysis->properties[$index]['type'] = $analysis->properties[$index]['bodyType'];
                unset($analysis->properties[$index]['bodyType']);
            }
        }
    }

    /**
     * Whether another signature in the shape may cover a key this one covers. Only two template patterns whose
     * leading or trailing literal text cannot both hold for one key are proven disjoint.
     *
     * @param  list<string>  $names
     */
    private function overlapsAnother(string $name, array $names): bool
    {
        $own = $this->literalSegments($name);

        foreach ($names as $other) {
            if ($other === $name) {
                continue;
            }

            $theirs = $this->literalSegments($other);

            if ($own === null || $theirs === null || ! $this->disjoint($own, $theirs)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether no key can match both patterns: every match starts with its first literal segment and ends with its last.
     *
     * @param  non-empty-list<string>  $a
     * @param  non-empty-list<string>  $b
     */
    private function disjoint(array $a, array $b): bool
    {
        [$headA, $tailA, $headB, $tailB] = [$a[0], $a[count($a) - 1], $b[0], $b[count($b) - 1]];

        return ! (str_starts_with($headA, $headB) || str_starts_with($headB, $headA))
            || ! (str_ends_with($tailA, $tailB) || str_ends_with($tailB, $tailA));
    }

    /** The regex a named key matches when this template-literal signature covers it, or null for any other key. */
    private function keyPattern(string $name): ?string
    {
        $segments = $this->literalSegments($name);

        if ($segments === null) {
            return null;
        }

        $quoted = array_map(fn (string $segment): string => preg_quote($segment, '/'), $segments);

        return '/^'.implode('.*', $quoted).'$/s';
    }

    /**
     * A template-literal signature's literal text between its `${string}` placeholders, or null for any other key.
     *
     * @return non-empty-list<string>|null
     */
    private function literalSegments(string $name): ?array
    {
        if (! JsEmitter::isIndexSignatureKey($name) || preg_match('/`(.*)`\]$/s', $name, $template) !== 1) {
            return null;
        }

        // A literal `${` is written `\${`, so only an unescaped one is a placeholder.
        $segments = preg_split('/(?<!\\\\)\$\{string\}/', $template[1]);

        if ($segments === false || $segments === []) {
            return null; // @codeCoverageIgnore
        }

        return array_map(fn (string $segment): string => str_replace('\\${', '${', $segment), $segments);
    }
}
