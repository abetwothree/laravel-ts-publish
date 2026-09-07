<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

/**
 * Ask structural questions about a TypeScript type string, and rewrite one: importable tokens,
 * top-level unions, null hoisting, same-basename aliasing, enum substitution, global qualification.
 *
 * @internal
 */
class TsTypeString
{
    /** @var list<string> */
    public const array TS_PRIMITIVES = [
        'string', 'number', 'boolean', 'bigint', 'symbol',
        'null', 'undefined', 'object', 'unknown', 'any', 'never', 'void',
    ];

    /**
     * Whether a resolved shape value contains an identifier that would need an import to be valid.
     *
     * extractImportableTypes() can't be reused: it skips '<'/'{' content, which docblock shapes routinely have.
     * Object-literal keys are stripped first so 'owner' in '{ owner: User }' isn't read as a value token.
     *
     * @param  list<string>  $importableNames  Local names an import already brings into the file.
     */
    public function shapeValueHasUnimportableToken(string $type, array $importableNames = []): bool
    {
        // The `?` of an optional key is not a token separator, so a key stripped without it survives as
        // `name?` and reads as an unimportable value.
        $withoutKeys = (string) preg_replace('/\b\w+\s*\??\s*:/', '', $type);

        $tokens = preg_split('/[<>{}()|,;\[\]\s]+/', $withoutKeys, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($tokens as $token) {
            if (in_array($token, self::TS_PRIMITIVES, true) || in_array($token, $importableNames, true)) {
                continue;
            }

            if (in_array($token, ['Record', 'Date', 'true', 'false'], true)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Extract importable type identifiers from a TypeScript type string,
     * filtering out primitives, inline types, and union syntax.
     *
     * @return list<string>
     */
    public function extractImportableTypes(string $typeString): array
    {
        $parts = explode('|', $typeString);
        $importable = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '' || in_array($part, self::TS_PRIMITIVES, true)) {
                continue;
            }

            if (str_starts_with($part, '{') || str_starts_with($part, '[') || str_contains($part, '<')) {
                continue;
            }

            $importable[] = str_ends_with($part, '[]') ? substr($part, 0, -2) : $part;
        }

        return array_values(array_unique($importable));
    }

    /**
     * Alias every bare type-name occurrence in one item's type string, walking occurrences left to right.
     *
     * A morph union's occurrence order is the order its FQCN list was built in, so same-basename FQCNs are
     * consumed in source order and the last one covers any further occurrence. No bare token survives.
     *
     * @param  list<string>  $itemFqcns  FQCN per occurrence, in source order, never deduped — a caller may
     *                                   supply more entries than real occurrences (e.g. a merged superset);
     *                                   aliasPropertyType() consumes only the matching prefix, in order
     * @param  array<string, string>  $nameMap  FQCN => unaliased type name
     * @param  array<string, string>  $aliases  FQCN => alias, for the subset that was aliased
     */
    public function aliasPropertyType(string $type, array $itemFqcns, array $nameMap, array $aliases): string
    {
        /** @var array<string, non-empty-list<string>> $queues */
        $queues = [];

        foreach ($itemFqcns as $fqcn) {
            $name = $nameMap[$fqcn] ?? null;

            if ($name !== null) {
                $queues[$name][] = $aliases[$fqcn] ?? $name;
            }
        }

        if ($queues === []) {
            return $type;
        }

        $names = array_keys($queues);
        usort($names, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $names = array_map(static fn (string $name): string => preg_quote($name, '/'), $names);

        $pattern = '/(?<![A-Za-z0-9_$.])(?:'.implode('|', $names).')(?![A-Za-z0-9_$])/';
        $cursors = [];

        return preg_replace_callback($pattern, static function (array $match) use ($queues, &$cursors): string {
            $name = $match[0];
            $cursor = $cursors[$name] ?? 0;
            $cursors[$name] = min($cursor + 1, count($queues[$name]) - 1);

            return $queues[$name][$cursor];
        }, $type) ?? $type;
    }

    /**
     * Prefix unqualified type names in a TypeScript type string with their global namespace.
     *
     * Pass 1 resolves per-file import aliases (`CrmUser` → `models.User`) first, so aliased names
     * reach the namespace-qualification pass already resolved.
     *
     * @param  string  $typeStr  The TypeScript type string to rewrite.
     * @param  array<string, list<string>>  $namespacedTypes  Map of namespace prefix → type names it owns.
     * @param  string  $skipNamespace  Skip types that already belong to this namespace (current context).
     * @param  array<string, string>  $aliasResolution  Per-file alias → 'namespace.OriginalName' map.
     */
    public function qualifyGlobalType(string $typeStr, array $namespacedTypes, string $skipNamespace = '', array $aliasResolution = []): string
    {
        // Pass 1: resolve per-file import aliases to their namespace-qualified equivalents
        foreach ($aliasResolution as $alias => $qualified) {
            $lastDot = strrpos($qualified, '.');
            $targetNs = $lastDot !== false ? substr($qualified, 0, $lastDot) : '';
            $replacement = ($targetNs === $skipNamespace)
                ? substr($qualified, $lastDot + 1)
                : $qualified;
            $pattern = '/(?<![A-Za-z0-9_$.])'.preg_quote($alias, '/').'(?![A-Za-z0-9_$])/';
            $typeStr = preg_replace($pattern, $replacement, $typeStr) ?? $typeStr;
        }

        // Pass 2: names that also exist in the skip namespace belong to the current context,
        // so they must not be re-qualified with another namespace.
        /** @var list<string> $skipTypeNames */
        $skipTypeNames = $namespacedTypes[$skipNamespace] ?? [];

        foreach ($namespacedTypes as $namespace => $typeNames) {
            if ($namespace === $skipNamespace) {
                continue;
            }

            // Match longer names first to avoid partial replacements (e.g. 'StatusType' before 'Status')
            usort($typeNames, fn (string $a, string $b): int => strlen($b) - strlen($a));

            foreach ($typeNames as $typeName) {
                if (in_array($typeName, $skipTypeNames, true)) {
                    continue;
                }

                $pattern = '/(?<![A-Za-z0-9_$.])'.preg_quote($typeName, '/').'(?![A-Za-z0-9_$])/';
                $typeStr = preg_replace($pattern, $namespace.'.'.$typeName, $typeStr) ?? $typeStr;
            }
        }

        return $typeStr;
    }

    /**
     * Split a type string into its top-level union members.
     *
     * Depth-aware over braces, parens, angle brackets, and square brackets, and skips
     * quoted literals whole, so a nested `|` never splits.
     *
     * @return list<string>
     */
    public function splitTopLevelUnion(string $typeStr): array
    {
        return TsTypeShape::splitTopLevel($typeStr, ['|']);
    }

    /**
     * Joins union members with a single trailing `null`, whichever arms the nulls came from.
     *
     * @param  list<string>  $types
     */
    public function hoistNull(array $types): string
    {
        $members = [];
        $nullable = false;

        foreach ($types as $type) {
            foreach ($this->splitTopLevelUnion($type) as $member) {
                if ($member === 'null') {
                    $nullable = true;

                    continue;
                }

                $members[] = $member;
            }
        }

        $members = array_unique($members);

        if ($nullable) {
            $members[] = 'null';
        }

        return implode(' | ', $members);
    }

    /**
     * Whether a TypeScript type name occurs as its own token, not inside a longer identifier.
     *
     * Only a leading `.` disqualifies: `foo.StatusType` is a property read, while `StatusType.foo`
     * reads a member of the type and so still names it.
     */
    public function typeNameOccursIn(string $typeName, string $haystack): bool
    {
        return preg_match('/(?<![A-Za-z0-9_$.])'.preg_quote($typeName, '/').'(?![A-Za-z0-9_$])/', $haystack) === 1;
    }

    /**
     * Replace a bare enum type-name token with its AsEnum wrap, preserving every other union arm.
     *
     * The lookbehind's `.` keeps a namespace-qualified `foo.RoleType` unmatched; the lookahead keeps
     * `RoleTypeExtra` unmatched.
     */
    public function substituteEnumType(string $typeStr, string $bareTypeName, string $asEnumType): string
    {
        $pattern = '/(?<![A-Za-z0-9_$.])'.preg_quote($bareTypeName, '/').'(?![A-Za-z0-9_$])/';

        return preg_replace($pattern, $asEnumType, $typeStr) ?? $typeStr;
    }

    /**
     * Replace `AsEnum<typeof ConstAlias>` patterns with the pre-computed type alias.
     *
     * In the globals file there is no `AsEnum` import, so `AsEnum<typeof X>` and `XType` collapse to
     * the same qualified name — the pair must be folded first to avoid emitting a literal duplicate.
     *
     * @param  string  $typeStr  The TypeScript type string to rewrite.
     * @param  array<string, string>  $constToTypeMap  constAlias => 'namespace.TypeName'
     */
    public function rewriteAsEnumToType(string $typeStr, array $constToTypeMap): string
    {
        foreach ($constToTypeMap as $constAlias => $qualifiedTypeName) {
            $lastDot = strrpos($qualifiedTypeName, '.');
            $bareTypeName = $lastDot === false ? $qualifiedTypeName : substr($qualifiedTypeName, $lastDot + 1);

            // A trailing `[` means the bare arm is array-shaped and the AsEnum arm is not (or vice
            // versa) — a genuinely different pair, not the redundant same-shaped one this folds.
            $pairPattern = '/AsEnum<typeof\s+'.preg_quote($constAlias, '/').'\s*>\s*\|\s*'
                .preg_quote($bareTypeName, '/').'(?![A-Za-z0-9_$\[])/';
            $typeStr = preg_replace($pairPattern, $qualifiedTypeName, $typeStr) ?? $typeStr;

            // Same pair reversed: the bare alias would be re-qualified afterwards, recreating the duplicate.
            // Only ResourceTransformer::rewriteEnumResourceTypes() emits this pair shape, always forward-ordered.
            $reversedPattern = '/(?<![A-Za-z0-9_$.])'.preg_quote($bareTypeName, '/').'\s*\|\s*AsEnum<typeof\s+'
                .preg_quote($constAlias, '/').'\s*>/';
            $typeStr = preg_replace($reversedPattern, $qualifiedTypeName, $typeStr) ?? $typeStr;

            $singlePattern = '/AsEnum<typeof\s+'.preg_quote($constAlias, '/').'\s*>/';
            $typeStr = preg_replace($singlePattern, $qualifiedTypeName, $typeStr) ?? $typeStr;
        }

        return $typeStr;
    }

    /**
     * A "vague" TS type carries no element information, so a docblock generic can usually do better.
     *
     * An object-literal shape is never vague even when a key resolves to 'unknown' — a bare 'unknown'
     * substring only signals vagueness outside `{...}`, where no per-key structure exists.
     */
    public function isVagueTsType(string $type): bool
    {
        return $type === 'object' || (str_contains($type, 'unknown') && ! str_contains($type, '{'));
    }
}
