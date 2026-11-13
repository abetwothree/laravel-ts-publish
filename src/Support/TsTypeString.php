<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\IndexSignatureKey;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\TypeScanner;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;

/**
 * Ask structural questions about a TypeScript type string, and rewrite one: importable tokens,
 * top-level unions, null hoisting, same-basename aliasing, enum substitution, global qualification.
 *
 * @phpstan-import-type SiblingImportNames from ImportNameRegistry
 * @phpstan-import-type TypesImportMap from Datable
 * @phpstan-import-type LiteralSpan from TypeScanner
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

    /** The keyword types besides TS_PRIMITIVES that an identifier pattern would read as a name. */
    protected const array KEYWORD_TYPES = ['true', 'false', 'this'];

    /** The union arms that say nothing of a value, as an untyped read or an `'array'` cast publishes it. */
    protected const array VAGUE_ARMS = ['unknown', 'unknown[]', 'never[]', 'Record<string, unknown>'];

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
        // from an int or constant array key), a generated index signature and the open marker are keys too.
        $withoutKeys = (string) preg_replace(
            '/(?:'.IndexSignatureKey::PATTERN.'|'.preg_quote(VisibleGaps::MARKER_KEY, '/').'|\b\w+)\s*\??\s*:/',
            '',
            $this->withoutQuotedKeys($type),
        );

        // A literal's text and a number need no import, whatever they spell; a template literal's placeholder may.
        $withoutLiterals = (string) preg_replace(
            '/(?<![\w$.])-?\d+(?:\.\d+)?(?![\w$.])/',
            ' ',
            TypeScanner::maskLiterals($withoutKeys),
        );

        $tokens = preg_split('/[<>{}()|,;\[\]\s]+/', $withoutLiterals, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($tokens as $token) {
            if (in_array($token, self::TS_PRIMITIVES, true) || in_array($token, $importableNames, true)) {
                continue;
            }

            // A gap is stripped before a type is published, so it is never imported.
            if (VisibleGaps::isGap($token)) {
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
     * Whether a shape value's own custom imports bring in every name it spells, a composed class's marker included, so
     * a shape that carries custom imports and no FQCN channel may keep it.
     *
     * @param  TypesImportMap  $customImports
     */
    public function importsCover(string $type, array $customImports): bool
    {
        return ! $this->shapeValueHasUnimportableToken($type, ImportMaps::localNames($customImports));
    }

    /**
     * Extract the importable type names from a TypeScript type string: each union part, read past a leading `keyof`,
     * `typeof`, `readonly` or `unique` and its trailing `[]`s, counts only when one non-primitive identifier is left
     * that is no gap arm.
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

            $named = ! in_array($name, self::TS_PRIMITIVES, true) && ! VisibleGaps::isGap($name);

            if (! $named || preg_match($identifier, $name) !== 1) {
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
     * @param  SiblingImportNames  $siblings  each sibling name an FQCN is imported under, such as an enum's `…Kind`
     */
    public function aliasPropertyType(string $type, array $itemFqcns, array $nameMap, array $aliases, array $siblings = []): string
    {
        return $this->aliasInQueueOrder($type, $itemFqcns, $nameMap, $aliases, '', $siblings);
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
     * The byte spans, end exclusive and in order, of a type's string literals, its template literals' text and its
     * generated index signature keys: text that is a value or a key, never a name.
     *
     * @return list<LiteralSpan>
     */
    public function literalSpans(string $type): array
    {
        $spans = TypeScanner::literalSpans($type);
        $keyPattern = '/'.IndexSignatureKey::PATTERN.'/';

        if (! str_contains($type, ': ') || preg_match_all($keyPattern, $type, $keys, PREG_OFFSET_CAPTURE) === 0) {
            return $spans;
        }

        foreach ($keys[0] as [$key, $at]) {
            $inside = static fn (array $span): bool => $at >= $span[0] && $at < $span[1];

            // A key spelled inside a literal is that literal's text; the key's own template text is the key's.
            if (! array_any($spans, $inside)) {
                $end = $at + strlen($key);
                $outside = static fn (array $span): bool => $span[1] <= $at || $span[0] >= $end;
                $spans = [...array_filter($spans, $outside), [$at, $end]];
            }
        }

        usort($spans, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return $spans;
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

        $members = array_values(array_unique($members));

        if ($nullable) {
            $members[] = 'null';
        }

        return count($members) === 1 ? $members[0] : implode(' | ', array_map(TsTypeShape::unionArm(...), $members));
    }

    /**
     * Joins members into one union: `never` when there is none, as an empty `type X = ;` does not compile.
     *
     * @param  list<int|string>  $members  Type strings, or the int literal types of an int-backed enum's values.
     */
    public function union(array $members): string
    {
        return $members === [] ? 'never' : implode(' | ', $members);
    }

    /**
     * The type with an `undefined` arm appended, unless it already has one at the top level: an `undefined` inside a
     * shape, a `Record` or a string literal does not admit an absent key.
     */
    public function orUndefined(string $type): string
    {
        return $this->withArm($type, 'undefined');
    }

    /**
     * Whether a type has a top-level `null` arm; one in a shape, a generic, an element type or literal text does not.
     */
    public function hasNullArm(string $type): bool
    {
        return in_array('null', $this->splitTopLevelUnion($type), true);
    }

    /**
     * The type with a `null` arm appended unless it has a top-level one; `unknown` already admits null, so it stays.
     */
    public function withNullArm(string $type): string
    {
        return $type === 'unknown' ? $type : $this->orNull($type);
    }

    /**
     * A type with a `null` arm when another type has a top-level one, as withNullArm() appends it.
     */
    public function withNullArmOf(string $from, string $to): string
    {
        return $this->hasNullArm($from) ? $this->withNullArm($to) : $to;
    }

    /**
     * The type with a `null` arm appended unless it has a top-level one, `unknown` too, as a nullable PHP type prints.
     */
    public function orNull(string $type): string
    {
        // Never hoistNull(): it de-duplicates arms, so `User | User`, two classes, would lose one.
        return $this->withArm($type, 'null');
    }

    /**
     * The type without its top-level `null` arms, `unknown` when no arm or only gap arms are left; a nested `null`
     * stays.
     */
    public function withoutNullArm(string $type): string
    {
        $arms = array_values(array_diff($this->splitTopLevelUnion($type), ['null']));
        $onlyGaps = VisibleGaps::holds($type) && array_all($arms, VisibleGaps::isGap(...));

        return $arms === [] || $onlyGaps ? 'unknown' : implode(' | ', $arms);
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
     * Whether a type says nothing of its value but that it may be null: it has an arm besides `null`, and each such
     * arm is one of VAGUE_ARMS.
     */
    public function isVagueOnly(string $type): bool
    {
        $arms = array_diff($this->splitTopLevelUnion($type), ['null']);

        return $arms !== [] && array_all($arms, static fn (string $arm): bool => in_array($arm, self::VAGUE_ARMS, true));
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
     * Whether a type can join an interface's `extends` list: one type reference, optionally dotted or generic, and
     * never a keyword type or a `Record<…>`.
     */
    public function isExtendable(string $type): bool
    {
        $type = trim($type);
        $open = strpos($type, '<');
        $name = $open === false ? $type : rtrim(substr($type, 0, $open));

        // A record's index signature fails beside another contributor's named member of a different type (TS2411).
        if (in_array($name, [...static::KEYWORD_TYPES, 'Record'], true)
            || ($open !== false && ! TsTypeShape::isWrapped(substr($type, $open), '<', '>'))) {
            return false;
        }

        return array_all(explode('.', $name), fn (string $segment): bool => $this->extractImportableTypes($segment) === [$segment]);
    }

    /**
     * Whether a docblock's type replaces a declared one: the declaration is unknown or vague, and the docblock is
     * neither, or spells the declaration's list as the record a string-keyed array encodes as.
     */
    public function documentedWins(string $declared, string $documented): bool
    {
        return $this->isVagueTsType($declared)
            && (! $this->isVagueTsType($documented) || $this->spellsListAsRecord($declared, $documented));
    }

    /**
     * Whether a type is a vague list type's arms with an `unknown[]` spelled as a `Record<…>`: a string-keyed array
     * encodes as an object, never as the list a native `array` publishes.
     */
    public function spellsListAsRecord(string $list, string $record): bool
    {
        $arms = $this->splitTopLevelUnion($record);
        $listed = array_map(
            fn (string $arm): string => TsTypeShape::recordArguments($arm) === null ? $arm : 'unknown[]',
            $arms,
        );
        $spellsRecord = $listed !== $arms;
        $listArms = $this->splitTopLevelUnion($list);

        sort($listed);
        sort($listArms);

        return $spellsRecord && $listed === $listArms;
    }

    /**
     * Replace each registered name with its alias, the Nth occurrence of a name taking the Nth FQCN queued under it.
     *
     * A sibling name reads its class's queue, so an enum's `…Type` and `…Kind` tokens share one order. $anchor is a
     * pattern each occurrence must follow; a `\K` in it keeps the anchor out of the text replaced.
     *
     * @param  list<string>  $itemFqcns
     * @param  array<string, string>  $nameMap  FQCN => unaliased name
     * @param  array<string, string>  $aliases  FQCN => alias, for the subset that was aliased
     * @param  SiblingImportNames  $siblings
     */
    protected function aliasInQueueOrder(
        string $type,
        array $itemFqcns,
        array $nameMap,
        array $aliases,
        string $anchor,
        array $siblings = [],
    ): string {
        /** @var array<string, non-empty-list<string>> $queues name => the FQCN behind each of its occurrences */
        $queues = [];
        /** @var array<string, string> $queueOf each name a token is spelled with => the name its queue is kept under */
        $queueOf = [];

        foreach ($itemFqcns as $fqcn) {
            $name = $nameMap[$fqcn] ?? null;

            if ($name !== null) {
                $queues[$name][] = $fqcn;
                $queueOf[$name] = $name;

                foreach (array_keys($siblings[$fqcn] ?? []) as $sibling) {
                    $queueOf[$sibling] ??= $name;
                }
            }
        }

        if ($queueOf === []) {
            return $type;
        }

        $pattern = $this->queuedTokenPattern(array_keys($queueOf), $anchor);
        $seen = [];

        return preg_replace_callback($pattern, function (array $match) use ($queues, $queueOf, $aliases, $siblings, &$seen): string {
            $spelled = $match[0];
            $name = $queueOf[$spelled];
            $occurrence = $seen[$name] ?? 0;
            $seen[$name] = $occurrence + 1;
            $fqcn = $queues[$name][$this->queuePosition($occurrence, count($queues[$name]))];

            return $spelled === $name ? ($aliases[$fqcn] ?? $name) : ($siblings[$fqcn][$spelled] ?? $spelled);
        }, $type) ?? $type;
    }

    /**
     * Qualify one type string under the given maps, leaving each literal's text as written.
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
        $segments = [];
        $from = 0;

        foreach ($this->literalSpans($typeStr) as [$start, $end]) {
            array_push($segments, substr($typeStr, $from, $start - $from), substr($typeStr, $start, $end - $start));
            $from = $end;
        }

        $segments[] = substr($typeStr, $from);
        $qualifiable = array_filter($segments, static fn (int $index): bool => $index % 2 === 0, ARRAY_FILTER_USE_KEY);

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

    /**
     * The type without each quoted key, its `?` and its `:`: a string literal, as the scanner reads one, followed by
     * a colon.
     */
    private function withoutQuotedKeys(string $type): string
    {
        foreach (array_reverse(TypeScanner::literalSpans($type)) as [$start, $end]) {
            if (str_contains('"\'', $type[$start]) && preg_match('/\G\s*\??\s*:/', $type, $colon, 0, $end) === 1) {
                $type = substr_replace($type, '', $start, $end - $start + strlen($colon[0]));
            }
        }

        return $type;
    }

    /**
     * The type with an arm appended unless it has that arm at the top level, each arm grouped as it joins.
     */
    private function withArm(string $type, string $arm): string
    {
        $arms = $this->splitTopLevelUnion($type);

        return in_array($arm, $arms, true) ? $type : implode(' | ', array_map(TsTypeShape::unionArm(...), $arms)).' | '.$arm;
    }
}
