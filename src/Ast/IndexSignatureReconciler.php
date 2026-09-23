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
     * Union each signature with the same-pattern keys beside it, or, where one of them cannot join the union, another
     * signature's pattern may overlap its own, or the published type inherits keys no analysis sees, put back the
     * value its body alone gives it.
     *
     * @param  array<string, string>  $castKeys  keys a publisher lays over the analysis, by type: each is read in
     *                                           place of the analysis's own type, and its channels are not checked
     * @param  bool  $inheritsUnseenKeys  the published type also extends an interface whose keys no analysis sees
     */
    public function reconcile(MethodAnalysis $analysis, array $castKeys = [], bool $inheritsUnseenKeys = false): void
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

        $segments = [];

        foreach (array_keys($signatures) as $name) {
            $parsed = $this->literalSegments($name);

            if ($parsed !== null) {
                $segments[$name] = $parsed;
            }
        }

        $published = array_replace($published, $castKeys);
        $dropped = [];

        foreach ($signatures as $name => $indexes) {
            if (! isset($segments[$name])) {
                continue;
            }

            if ($inheritsUnseenKeys) {
                $this->restoreBodyTypes($analysis, $indexes);

                continue;
            }

            $pattern = $this->keyPattern($segments[$name]);
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

            $overlaps = $this->overlapsAnother($name, $segments[$name], array_keys($signatures), $segments);

            if (! $overlaps && count($types) === 1) {
                continue;
            }

            $arms = $overlaps ? null : $this->unionArms($analysis, $keys, $types, $castKeys);

            if ($arms === null) {
                $this->restoreBodyTypes($analysis, $indexes);
            } else {
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
     * Every arm of the given types except `undefined`, or null when one of them cannot join: a top-level `unknown`
     * arm swallows the rest, a token other than a primitive, `Record`, `Date` or a literal, or an FQCN channel, is
     * rewritten under its own key's name, and the splitter can cut a string literal holding a backslash apart.
     *
     * @param  list<string>  $keys  the signature's name and the named keys its pattern matches
     * @param  list<string>  $types  the signature's entries' types and those keys' types
     * @param  array<string, string>  $castKeys  keys whose published type is a cast, so no channel of theirs counts
     * @return list<string>|null
     */
    private function unionArms(MethodAnalysis $analysis, array $keys, array $types, array $castKeys): ?array
    {
        foreach ($keys as $key) {
            if (! isset($castKeys[$key]) && $analysis->hasFqcnChannel($key)) {
                return null;
            }
        }

        $arms = [];

        foreach ($types as $type) {
            if ($this->holdsEscapedLiteral($type)
                || TsTypeString::shapeValueHasUnimportableToken($this->literalsAsPrimitives($type))) {
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

    /** The type with each string and number literal read as its primitive: a literal needs no import. */
    private function literalsAsPrimitives(string $type): string
    {
        $type = (string) preg_replace('/\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"/', 'string', $type);

        return (string) preg_replace('/(?<![\w$.])-?\d+(?:\.\d+)?(?![\w$.])/', 'number', $type);
    }

    /** Whether a `'` or `"` in the type is followed by a backslash before the next quote of its kind. */
    private function holdsEscapedLiteral(string $type): bool
    {
        return preg_match('/([\'"])(?:(?!\1)[^\\\\])*\\\\/', $type) === 1;
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
     * @param  non-empty-list<string>  $own
     * @param  list<string>  $names  every signature name in the shape
     * @param  array<string, non-empty-list<string>>  $segments  each template-literal signature's literal segments
     */
    private function overlapsAnother(string $name, array $own, array $names, array $segments): bool
    {
        foreach ($names as $other) {
            if ($other !== $name && (! isset($segments[$other]) || ! $this->disjoint($own, $segments[$other]))) {
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

    /**
     * The regex a named key matches when a template-literal signature with these literal segments covers it.
     *
     * @param  non-empty-list<string>  $segments
     */
    private function keyPattern(array $segments): string
    {
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

        // The name writes a literal `\` as `\\` and `${` as `\${`: a `${string}` no escape consumes is a placeholder.
        $segments = preg_split('/\\\\.(*SKIP)(*FAIL)|\$\{string\}/s', $template[1]);

        if ($segments === false || $segments === []) {
            return null; // @codeCoverageIgnore
        }

        return array_map(
            fn (string $segment): string => (string) preg_replace('/\\\\(.)/s', '$1', $segment),
            $segments,
        );
    }
}
