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

    /** The spelling of an object with no keys: TypeScript's `{}` accepts any value but null and undefined. */
    public const string EMPTY_OBJECT = 'Record<string, never>';

    /**
     * A character TypeScript reads as part of an identifier: Unicode ID_Continue, `$`, ZWNJ and ZWJ, plus U+30FB and
     * U+FF65, which joined ID_Continue in Unicode 15.1 and so are missing from an older PCRE2's tables.
     */
    protected const string IDENTIFIER_CHARACTER = '[\p{ID_Continue}$\x{200C}\x{200D}\x{30FB}\x{FF65}]';

    /**
     * The same set for a PCRE2 before 10.40, which lacks `\p{ID_Continue}`: its categories and Other_ID code points,
     * less U+2E2F, the one letter Unicode makes pattern syntax and so never an identifier character.
     */
    protected const string IDENTIFIER_CHARACTER_FALLBACK = '(?:(?!\x{2E2F})[\p{L}\p{Nl}\p{Mn}\p{Mc}\p{Nd}\p{Pc}$'
        .'\x{200C}\x{200D}\x{B7}\x{387}\x{1369}-\x{1371}\x{19DA}\x{2118}\x{212E}\x{309B}\x{309C}\x{30FB}\x{FF65}])';

    /** The identifier-character pattern this process's PCRE2 compiles, decided on first use. */
    protected static ?string $identifierCharacter = null;

    /**
     * The namespace and alias maps the memoized qualifications were made under. qualifyGlobalType() reads nothing
     * else, so its answers hold until a call brings other maps.
     *
     * @var array{array<string, list<string>>, array<string, string>}|null
     */
    protected ?array $qualificationMaps = null;

    /** @var array<string, string> skip namespace and type string => the qualified type string */
    protected array $qualifiedTypes = [];

    /**
     * The namespace map the two indexes below were read from.
     *
     * @var array<string, list<string>>|null
     */
    protected ?array $indexedNamespaces = null;

    /** @var array<string, string> type name => the first namespace that owns it */
    protected array $typeOwners = [];

    /** @var array<string, array<string, true>> namespace => the type names it owns */
    protected array $ownedTypes = [];

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
        // A key goes with its optional `?`, no token separator, or `name?` would read as a value. A quoted key (`"1"`,
        // from an int or constant array key) and a generated index signature are keys too, and nothing imports them.
        $withoutKeys = (string) preg_replace('/(?:'.IndexSignatureKey::PATTERN.'|\b\w+|"[^"]*")\s*\??\s*:/', '', $type);

        // A string or number literal needs no import, whatever it spells.
        $withoutLiterals = (string) preg_replace(
            ['/\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"/', '/(?<![\w$.])-?\d+(?:\.\d+)?(?![\w$.])/'],
            ' ',
            $withoutKeys,
        );

        $tokens = preg_split('/[<>{}()|,;\[\]\s]+/', $withoutLiterals, -1, PREG_SPLIT_NO_EMPTY) ?: [];

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
     * Extract the importable type names from a TypeScript type string: each union part, read past a leading `keyof`,
     * `typeof`, `readonly` or `unique` and its trailing `[]`s, counts only when one non-primitive identifier is left.
     *
     * @return list<string>
     */
    public function extractImportableTypes(string $typeString): array
    {
        $parts = explode('|', $typeString);
        $importable = [];
        // A digit or a combining mark continues an identifier but cannot start one.
        $identifier = '/^(?![\p{Nd}\p{Mn}\p{Mc}])'.$this->identifierCharacter().'+$/u';

        foreach ($parts as $part) {
            $name = (string) preg_replace(
                ['/^(?:(?:keyof|typeof|readonly|unique)\s+)+/', '/(?:\s*\[\s*\])+$/'],
                '',
                trim($part),
            );

            if (in_array($name, self::TS_PRIMITIVES, true) || preg_match($identifier, $name) !== 1) {
                continue;
            }

            $importable[] = $name;
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
        return $this->aliasInQueueOrder($type, $itemFqcns, $nameMap, $aliases, '');
    }

    /**
     * Alias every `typeof <const>` occurrence in one item's type string, in the queue order aliasPropertyType() uses.
     *
     * A type string spells a const only after `typeof`, as in `AsEnum<typeof Const>`. The same name bare is a type, and
     * can be another enum's: an inline array may read one enum bare and wrap another whose const has that name.
     *
     * @param  list<string>  $itemFqcns  FQCN per `typeof` occurrence, in source order, never deduped
     * @param  array<string, string>  $constNames  FQCN => unaliased const name
     * @param  array<string, string>  $aliases  FQCN => alias, for the subset that was aliased
     */
    public function aliasTypeofConst(string $type, array $itemFqcns, array $constNames, array $aliases): string
    {
        return $this->aliasInQueueOrder($type, $itemFqcns, $constNames, $aliases, 'typeof\s+\K');
    }

    /**
     * The pattern that reads a name as a whole token of a type string: not inside a longer name, not after a dot.
     * $anchor is a pattern each token must follow; a `\K` in it keeps the anchor out of the match.
     *
     * Longer names come first: the lookahead stops at ASCII, so a name that a longer one continues with a non-ASCII
     * letter is read whole only that way.
     *
     * @param  non-empty-list<string>  $names
     */
    public function queuedTokenPattern(array $names, string $anchor = ''): string
    {
        usort($names, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $names = array_map(static fn (string $name): string => preg_quote($name, '/'), $names);

        return '/(?<![A-Za-z0-9_$.])'.$anchor.'(?:'.implode('|', $names).')(?![A-Za-z0-9_$])/';
    }

    /**
     * The queue position the Nth occurrence of a name reads, counting from zero: the last entry covers every occurrence
     * after the queue runs out.
     *
     * @param  positive-int  $entries
     */
    public function queuePosition(int $occurrence, int $entries): int
    {
        return min($occurrence, $entries - 1);
    }

    /**
     * Prefix unqualified type names in a TypeScript type string with their global namespace.
     *
     * An alias in the file's own map (`CrmUser` → `models.User`) resolves through it, in the same pass as every other
     * name. A quoted string literal is left as written.
     *
     * @param  string  $typeStr  The TypeScript type string to rewrite.
     * @param  array<string, list<string>>  $namespacedTypes  Map of namespace prefix → type names it owns.
     * @param  string  $skipNamespace  Skip types that already belong to this namespace (current context).
     * @param  array<string, string>  $aliasResolution  Per-file alias → 'namespace.OriginalName' map.
     */
    public function qualifyGlobalType(string $typeStr, array $namespacedTypes, string $skipNamespace = '', array $aliasResolution = []): string
    {
        // The globals file passes the same two maps for every property, so most calls repeat a type already qualified.
        if ($this->qualificationMaps !== [$namespacedTypes, $aliasResolution]) {
            $this->qualificationMaps = [$namespacedTypes, $aliasResolution];
            $this->qualifiedTypes = [];
        }

        return $this->qualifiedTypes[$skipNamespace."\0".$typeStr]
            ??= $this->qualifyGlobalTypeOnce($typeStr, $namespacedTypes, $skipNamespace, $aliasResolution);
    }

    /**
     * Drop the memoized global qualifications, when a publish run starts.
     */
    public function forgetQualifiedTypes(): void
    {
        $this->qualificationMaps = null;
        $this->qualifiedTypes = [];
        $this->indexedNamespaces = null;
        $this->typeOwners = [];
        $this->ownedTypes = [];
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
     * The type with an `undefined` arm appended, unless it already has one at the top level: an `undefined` inside a
     * shape, a `Record` or a string literal does not admit an absent key.
     */
    public function orUndefined(string $type): string
    {
        return in_array('undefined', $this->splitTopLevelUnion($type), true) ? $type : $type.' | undefined';
    }

    /**
     * Whether a type name occurs as its own token in any of the given types, wherever it stands: a name inside a
     * string, template or comment counts too, since a kept import is unused at worst while a hidden one breaks the
     * generated file. `foo.StatusType` is a member access and `CrmStatusType` a longer name; `...StatusType[]` counts.
     */
    public function typeNameOccursIn(string $typeName, string ...$types): bool
    {
        // An identifier character on either side joins the name, and a `.` after one is a member access. `[...User]`
        // still counts, as does `import('x').User`; a name right after a numeric literal (`1User`) is missed.
        $class = $this->identifierCharacter();
        $pattern = '/(?<!'.$class.')(?<!'.$class.'\.)'.preg_quote($typeName, '/').'(?!'.$class.')/u';

        foreach ($types as $type) {
            $decoded = $this->decodeIdentifierEscapes($type);

            // A type PCRE cannot decode or read (invalid UTF-8) keeps the name rather than drops its import.
            if ($decoded === null || preg_match($pattern, $decoded) !== 0) {
                return true;
            }
        }

        return false;
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
     * Whether a type is `unknown` once its `null` arms are removed, as a `static|null` docblock reflects.
     *
     * TypeScript already reads `unknown | null` as `unknown`, so a handler declining one loses nothing.
     */
    public function isUnknownOnly(string $type): bool
    {
        return array_values(array_diff($this->splitTopLevelUnion($type), ['null'])) === ['unknown'];
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

    /**
     * Replace each registered name with its alias, the Nth occurrence of a name taking the Nth FQCN queued under it.
     *
     * $anchor is a pattern each occurrence must follow; a `\K` in it keeps the anchor out of the text replaced.
     *
     * @param  list<string>  $itemFqcns
     * @param  array<string, string>  $nameMap  FQCN => unaliased name
     * @param  array<string, string>  $aliases  FQCN => alias, for the subset that was aliased
     */
    protected function aliasInQueueOrder(string $type, array $itemFqcns, array $nameMap, array $aliases, string $anchor): string
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

        $pattern = $this->queuedTokenPattern(array_keys($queues), $anchor);
        $seen = [];

        return preg_replace_callback($pattern, function (array $match) use ($queues, &$seen): string {
            $name = $match[0];
            $occurrence = $seen[$name] ?? 0;
            $seen[$name] = $occurrence + 1;

            return $queues[$name][$this->queuePosition($occurrence, count($queues[$name]))];
        }, $type) ?? $type;
    }

    /**
     * Qualify one type string under the given maps, leaving each quoted string literal as written.
     *
     * Each name is read once: through the file's own map when it has an entry, else as the current namespace's own
     * name, else as the first namespace that owns it. A name after a `.` is already qualified.
     *
     * @param  array<string, list<string>>  $namespacedTypes
     * @param  array<string, string>  $aliasResolution
     */
    protected function qualifyGlobalTypeOnce(string $typeStr, array $namespacedTypes, string $skipNamespace, array $aliasResolution): string
    {
        // A literal's text is a value, so 'Post' must stay 'Post', and a nested index signature's is a key, kept whole.
        // A template literal is matched only so a quote inside it opens no string; its own text is qualified as usual.
        $literal = '/('.IndexSignatureKey::PATTERN.'|\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"|`(?:[^`\\\\]|\\\\.)*`)/s';
        $segments = preg_split($literal, $typeStr, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$typeStr];
        $qualifiable = array_filter(
            $segments,
            static fn (string $segment, int $index): bool => $index % 2 === 0 || str_starts_with($segment, '`'),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($this->indexedNamespaces !== $namespacedTypes) {
            $this->indexNamespaces($namespacedTypes);
        }

        $owners = $this->typeOwners;
        $own = $this->ownedTypes[$skipNamespace] ?? [];

        $qualified = preg_replace_callback(
            '/(?<![A-Za-z0-9_$.\x80-\xff])[A-Za-z_$\x80-\xff][A-Za-z0-9_$\x80-\xff]*/',
            static function (array $match) use ($aliasResolution, $skipNamespace, $owners, $own): string {
                $name = $match[0];

                if (isset($aliasResolution[$name])) {
                    $target = $aliasResolution[$name];
                    $lastDot = strrpos($target, '.');

                    return $lastDot !== false && substr($target, 0, $lastDot) === $skipNamespace
                        ? substr($target, $lastDot + 1)
                        : $target;
                }

                return isset($own[$name]) || ! isset($owners[$name]) ? $name : $owners[$name].'.'.$name;
            },
            $qualifiable,
        );

        return implode('', array_replace($segments, $qualified));
    }

    /**
     * Index a namespace map by type name, once per map: who owns each name first, and what each namespace owns.
     *
     * @param  array<string, list<string>>  $namespacedTypes
     */
    protected function indexNamespaces(array $namespacedTypes): void
    {
        $this->indexedNamespaces = $namespacedTypes;
        $this->typeOwners = [];
        $this->ownedTypes = [];

        foreach ($namespacedTypes as $namespace => $typeNames) {
            $this->ownedTypes[$namespace] = array_fill_keys($typeNames, true);

            foreach ($typeNames as $typeName) {
                $this->typeOwners[$typeName] ??= $namespace;
            }
        }
    }

    /**
     * The identifier-character pattern this PCRE2 compiles: `\p{ID_Continue}` from 10.40 on, its stand-in before.
     */
    protected function identifierCharacter(): string
    {
        // Probed once per process, silenced so an older PCRE2 falls back instead of its warning failing the command.
        return static::$identifierCharacter ??= @preg_match('/'.static::IDENTIFIER_CHARACTER.'/u', '') !== false
            ? static::IDENTIFIER_CHARACTER
            : static::IDENTIFIER_CHARACTER_FALLBACK;
    }

    /**
     * Spell each `\uXXXX` or `\u{X…}` escape as the character it names, as TypeScript reads one in a name; null when
     * PCRE cannot run the pattern.
     */
    protected function decodeIdentifierEscapes(string $type): ?string
    {
        if (! str_contains($type, '\\u')) {
            return $type;
        }

        $pattern = '/\\\\u(?:\{([0-9A-Fa-f]+)\}|([0-9A-Fa-f]{4}))/';

        return preg_replace_callback($pattern, static function (array $match): string {
            $codePoint = hexdec($match[1] !== '' ? $match[1] : $match[2]);
            $char = is_int($codePoint) && $codePoint <= 0x10FFFF ? mb_chr($codePoint, 'UTF-8') : false;

            // Every escape is decoded whatever this PCRE2's tables hold: one left as written runs its digits into the
            // next name. One naming no Unicode scalar value (a surrogate, past U+10FFFF) stays; TypeScript rejects it.
            return $char === false ? $match[0] : $char;
        }, $type);
    }
}
