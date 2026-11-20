<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support\TypeStrings;

use AbeTwoThree\LaravelTsPublish\Support\TsTypeString;
use Closure;

/**
 * A TypeScript type string read token by token, each name beside the role it plays there: a type it stands as, a
 * binder that declares one, or another word. Literal text is read as the scanner reads it, never as names.
 *
 * Names are read with the identifier pattern and escape decoder the caller's PCRE2 settles on.
 *
 * @phpstan-type TypeToken = array{string, bool}
 * @phpstan-type TypeRole = 'binder'|'other'|'type'
 *
 * @internal
 */
final readonly class TypeTokens
{
    /** The words a type reads as an operator or a modifier, never as a name. */
    private const array TYPE_OPERATORS = [
        'abstract', 'as', 'asserts', 'const', 'extends', 'get', 'import', 'in', 'infer', 'is', 'keyof', 'new', 'out',
        'readonly', 'set', 'typeof', 'unique',
    ];

    /** The tokens a type starts right after, so a name after one is a type even where a `:` follows it. */
    private const array TYPE_LEADS = [
        '?', '|', '&', '=>', '<', '=', 'abstract', 'as', 'asserts', 'extends', 'in', 'infer', 'is', 'keyof', 'new',
        'typeof', 'unique',
    ];

    /**
     * @param  string  $identifierCharacter  the pattern of one character TypeScript reads as part of an identifier
     * @param  Closure(string): ?string  $decodeEscapes  a text with each `\u` escape spelled as the character it names,
     *                                                   or null when PCRE cannot run its pattern
     */
    public function __construct(private string $identifierCharacter, private Closure $decodeEscapes) {}

    /**
     * The names a type string references in a type position, each once in the order it first spells them: the names a
     * file must bind for the type to compile.
     *
     * A member's, label's, parameter's or method's own name is none, nor is literal text, a primitive, a keyword, a gap
     * arm, a name after a `.`, a binder `infer`, a mapped type or a type parameter list declares, a predicate's
     * parameter, or a TypeScript lib global (TsLibGlobals): a type, a namespace before a `.`, or after `typeof` any of
     * them, a value included. The operand of `typeof` is one.
     *
     * @return list<string>
     */
    public function referencedTypeNames(string $type): array
    {
        $tokens = $this->tokens($type);
        $names = [];
        $binders = [];

        foreach ($this->roles($tokens) as $index => $role) {
            $token = $tokens[$index][0];

            // A global value, such as the DOM's `Image`, or a namespace, such as `Temporal`, is no type, so a type
            // position names the file's: a namespace is the lib's only before a `.`, and either only after `typeof`.
            $namespace = isset(TsLibGlobals::NAMESPACES[$token]);
            $global = isset(TsLibGlobals::TYPES[$token])
                || ($namespace && ($tokens[$index + 1][0] ?? '') === '.')
                || (($tokens[$index - 1][0] ?? '') === 'typeof' && ($namespace || isset(TsLibGlobals::VALUES[$token])));

            if ($role === 'binder') {
                $binders[] = $token;
            } elseif ($role === 'type' && ! $global && ! VisibleGaps::isGap($token)) {
                $names[] = $token;
            }
        }

        return array_values(array_unique(array_diff($names, $binders)));
    }

    /**
     * Respell each occurrence of a name that stands as a type or a binder declares, as referencedTypeNames() reads
     * them, by its alias, so a binder and its uses rename together: never literal text, a member's own name or a name
     * after a `.`.
     *
     * @param  array<string, string>  $aliases  name => alias
     */
    public function aliasReferences(string $type, array $aliases): string
    {
        if ($aliases === []) {
            return $type;
        }

        $tokens = $this->tokens($type);
        $isTypeAt = [];

        foreach ($this->roles($tokens) as $index => $role) {
            if (isset($aliases[$tokens[$index][0]])) {
                $isTypeAt[$tokens[$index][0]][] = $role !== 'other';
            }
        }

        // The same names the tokens read, in the same order: literal text is skipped, and an escape is read as a name.
        $class = $this->identifierCharacter;
        $pattern = '/(?<!'.$class.')(?![\p{Nd}\p{Mn}\p{Mc}])(?:'.$class.'|\\\\u(?:\{[0-9A-Fa-f]+\}|[0-9A-Fa-f]{4}))+/u';
        $spans = TypeScanner::literalSpans($type);

        if (preg_match_all($pattern, $type, $matches, PREG_OFFSET_CAPTURE) === false) {
            return $type;
        }

        $seen = [];
        $respelled = [];

        foreach ($matches[0] as [$spelled, $at]) {
            $name = ($this->decodeEscapes)($spelled) ?? $spelled;
            $inLiteral = array_any($spans, static fn (array $span): bool => $at >= $span[0] && $at < $span[1]);

            if (! isset($aliases[$name]) || $inLiteral) {
                continue;
            }

            $occurrence = $seen[$name] = ($seen[$name] ?? -1) + 1;

            if ($isTypeAt[$name][$occurrence] ?? false) {
                $respelled[] = [$at, strlen($spelled), $aliases[$name]];
            }
        }

        foreach (array_reverse($respelled) as [$at, $length, $alias]) {
            $type = substr_replace($type, $alias, $at, $length);
        }

        return $type;
    }

    /**
     * The role of each name token of a type, by its index: a `type` it stands as, a `binder` a type parameter list,
     * `infer` or a mapped type declares, or `other`: a keyword, a member's, label's, parameter's or method's own name,
     * an optional method's included, a name after a `.` or a predicate's parameter.
     *
     * @param  list<TypeToken>  $tokens
     * @return array<int, TypeRole>
     */
    private function roles(array $tokens): array
    {
        $keywords = [...TsTypeString::TS_PRIMITIVES, ...TsTypeString::KEYWORD_TYPES, ...self::TYPE_OPERATORS];
        $parameterLists = $this->parameterLists($tokens);
        $roles = [];
        $opened = [];

        foreach ($tokens as $index => [$token, $isName]) {
            if (! $isName) {
                match ($token) {
                    '{', '[', '(', '<' => $opened[] = $index,
                    '}', ']', ')', '>' => array_pop($opened),
                    default => null,
                };

                continue;
            }

            $before = $tokens[$index - 1][0] ?? '';
            $after = $tokens[$index + 1][0] ?? '';
            $afterNext = $tokens[$index + 2][0] ?? '';
            $leads = in_array($before, self::TYPE_LEADS, true);
            $enclosing = $opened === [] ? null : $opened[array_key_last($opened)];
            $enclosedBy = $enclosing === null ? '' : $tokens[$enclosing][0];
            $declared = $enclosing !== null && isset($parameterLists[$enclosing])
                && in_array($before, ['<', ',', 'const', 'in', 'out'], true);
            $roles[$index] = 'other';

            if ($before === '.' || in_array($token, $keywords, true)) {
                continue;
            }

            if ($declared || $before === 'infer' || ($after === 'in' && $enclosedBy === '[')) {
                $roles[$index] = 'binder';

                continue;
            }

            // A name before its `:` is a member's, label's or parameter's own unless a type starts right before it: a
            // conditional's true branch ends at a `:` too. A name after a `:` starts a value type, never a method.
            $binds = ($after === ':' || ($after === '?' && $afterNext === ':')) && ! $leads;
            $signature = $after === '?' ? $index + 2 : $index + 1;
            $opens = $tokens[$signature][0] ?? '';
            $method = ($opens === '(' || ($opens === '<' && isset($parameterLists[$signature])))
                && $enclosedBy === '{' && ! $leads && $before !== ':';
            $predicate = $before === 'asserts' || ($after === 'is' && ! in_array($afterNext, [':', '?'], true)
                && ($before === '=>' || ($before === ':' && ($tokens[$index - 2][0] ?? '') === ')')));
            $roles[$index] = $binds || $method || $predicate ? 'other' : 'type';
        }

        return $roles;
    }

    /**
     * The token index of each `<` that opens a type parameter list, the one a `(` follows once it closes: `<T>(x: T)`.
     *
     * @param  list<TypeToken>  $tokens
     * @return array<int, true>
     */
    private function parameterLists(array $tokens): array
    {
        $lists = [];
        $angles = [];

        foreach ($tokens as $index => [$token]) {
            if ($token === '<') {
                $angles[] = $index;
            } elseif ($token === '>' && $angles !== [] && ($tokens[$index + 1][0] ?? '') === '(') {
                $lists[(int) array_pop($angles)] = true;
            } elseif ($token === '>') {
                array_pop($angles);
            }
        }

        return $lists;
    }

    /**
     * A type's tokens, each beside whether it is a name: every name, `...`, `=>`, and every other character, with each
     * literal's text read as one `~`, as the scanner reads it, and each `\u` escape as the character it names.
     *
     * @return list<TypeToken>
     */
    private function tokens(string $type): array
    {
        $code = '';
        $from = 0;

        foreach (TypeScanner::literalSpans($type) as [$start, $end]) {
            $code .= substr($type, $from, $start - $from).'~';
            $from = $end;
        }

        $code .= substr($type, $from);
        $class = $this->identifierCharacter;
        // A digit or a combining mark continues a name but cannot start one, so `1User` holds no name.
        $pattern = '/(?<name>(?<!'.$class.')(?![\p{Nd}\p{Mn}\p{Mc}])'.$class.'+)|\.\.\.|=>|\S/u';

        $code = ($this->decodeEscapes)($code) ?? $code;

        if (preg_match_all($pattern, $code, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL) === false) {
            return [];
        }

        return array_map(static fn (array $match): array => [(string) $match[0], $match['name'] !== null], $matches);
    }
}
