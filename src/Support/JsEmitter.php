<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

use AbeTwoThree\LaravelTsPublish\Dtos\TsRouteDto;
use BackedEnum;
use Closure;
use InvalidArgumentException;
use JsonException;
use JsonSerializable;
use stdClass;
use UnitEnum;

/**
 * Turn PHP values and docblock text into JavaScript source: object keys, identifiers, literals,
 * route-argument objects, and JSDoc blocks.
 *
 * @phpstan-import-type RouteArgData from TsRouteDto
 *
 * @phpstan-type JsonData = null|bool|int|float|string|array<array-key, mixed>|stdClass
 *
 * @internal
 */
class JsEmitter
{
    /** @var list<string> */
    private const array RESERVED_JS_IDENTIFIERS = [
        'break', 'case', 'catch', 'class', 'const', 'continue', 'debugger',
        'default', 'delete', 'do', 'else', 'enum', 'export', 'extends', 'false',
        'finally', 'for', 'function', 'if', 'import', 'in', 'instanceof',
        'let', 'new', 'null', 'return', 'static', 'super', 'switch', 'this',
        'throw', 'true', 'try', 'typeof', 'var', 'void', 'while', 'with',
        'yield',
    ];

    /** json_encode()'s default depth, which also stops a jsonSerialize() that returns a fresh object every call. */
    private const int MAX_JSON_DEPTH = 512;

    /**
     * Spell a key as a JS object key: bare when it is an identifier, else as a quoted string.
     *
     * Pass $allowIndexSignature only in a type position: a generated `[key: number]`/`[key: string]` left unquoted
     * is a syntax error in a value position (an object literal).
     */
    public function validJsObjectKey(string $key, bool $allowIndexSignature = false): string
    {
        if (preg_match('/^[a-zA-Z_$][a-zA-Z0-9_$]*$/', $key)
            || ($allowIndexSignature && $this->isIndexSignatureKey($key))) {
            return $key;
        }

        // json_encode produces a properly escaped double-quoted string valid in JS/TS
        return (string) json_encode($key);
    }

    /**
     * Whether a key is a generated index signature; see IndexSignatureKey::is().
     */
    public function isIndexSignatureKey(string $key): bool
    {
        return IndexSignatureKey::is($key);
    }

    /**
     * Re-key #[TsCasts] entries to the published keys they retype, as castTargets() decides for their own keys.
     *
     * @template TCast
     *
     * @param  array<string, TCast>  $casts
     * @param  array<array-key, int|string>  $keys  the keys the casts are laid over
     * @return array<string, TCast>
     */
    public function castsByKey(array $casts, array $keys): array
    {
        return $this->retargetCasts($casts, $this->castTargets(array_keys($casts), $keys));
    }

    /**
     * The published key each #[TsCasts] key retypes: a key it equals, else the one index signature it spells another
     * way a user can write that name, or null where that signature is cast under its exact name or an earlier key.
     * The other spellings and the tie rules are in docs/components/support-helpers.md § `JsEmitter`.
     *
     * @param  list<string>  $castKeys
     * @param  array<array-key, int|string>  $keys  the keys the casts are laid over
     * @return array<string, string|null>
     */
    public function castTargets(array $castKeys, array $keys): array
    {
        $known = [];
        $spelledBy = [];

        foreach ($keys as $key) {
            $key = (string) $key;
            $known[$key] = true;

            if (str_contains($key, '\\') && $this->isIndexSignatureKey($key)) {
                foreach (IndexSignatureKey::castSpellings($key) as $spelling) {
                    $spelledBy[$spelling][$key] = true;
                }
            }
        }

        $cast = array_fill_keys($castKeys, true);
        $claimed = [];
        $targets = [];

        foreach ($castKeys as $castKey) {
            if (isset($known[$castKey]) || count($spelledBy[$castKey] ?? []) !== 1) {
                $targets[$castKey] = $castKey;

                continue;
            }

            $name = (string) array_key_first($spelledBy[$castKey]);
            $targets[$castKey] = isset($cast[$name]) || isset($claimed[$name]) ? null : $name;
            $claimed[$name] = true;
        }

        return $targets;
    }

    /**
     * Move each cast to the key castTargets() gave it, dropping one it gave none; a key it did not decide stays.
     *
     * @template TCast
     *
     * @param  array<string, TCast>  $casts
     * @param  array<string, string|null>  $targets
     * @return array<string, TCast>
     */
    public function retargetCasts(array $casts, array $targets): array
    {
        $moved = [];

        foreach ($casts as $key => $cast) {
            $target = array_key_exists($key, $targets) ? $targets[$key] : $key;

            if ($target !== null) {
                $moved[$target] = $cast;
            }
        }

        return $moved;
    }

    /**
     * Ensure a string is safe as a bare JS/TS identifier: a reserved word gains $suffix ('delete' → 'deleteMethod').
     *
     * Not for object property keys — reserved words are legal there in TS interfaces and literals.
     */
    public function safeJsIdentifier(string $name, string $suffix): string
    {
        if (in_array($name, self::RESERVED_JS_IDENTIFIERS, true)) {
            return $name.$suffix;
        }

        return $name;
    }

    /**
     * Convert a PHP value to a raw JavaScript/TypeScript literal.
     *
     * Unlike Js::from(), this emits readable object/array literals instead of JSON.parse(...) — the
     * output lands in generated .ts files, where XSS-safe encoding is not needed.
     *
     * An object other than a stdClass is spelled as jsonValue() reads it, so no hidden property reaches the file.
     */
    public function toJsLiteral(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new InvalidArgumentException('A non-finite float has no TypeScript literal.');
            }

            // (string) rounds to the `precision` ini value; json_encode() emits the shortest round-trip form.
            return (string) json_encode($value);
        }

        if (is_string($value)) {
            return "'".str_replace(['\\', "'", "\n", "\r", "\t"], ['\\\\', "\\'", '\\n', '\\r', '\\t'], $value)."'";
        }

        if ($value instanceof UnitEnum) {
            return $this->toJsLiteral($this->enumScalar($value));
        }

        if ($value instanceof stdClass) {
            return $this->objectLiteral(get_object_vars($value));
        }

        if (is_object($value)) {
            return $this->toJsLiteral($this->jsonValue($value));
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                return '['.implode(', ', array_map(fn ($v) => $this->toJsLiteral($v), $value)).']';
            }

            return $this->objectLiteral($value);
        }

        return 'null';
    }

    /**
     * The data json_encode() writes for a value, in the form toJsLiteral() spells the same way.
     *
     * A JsonSerializable value, an enum case among them, becomes its jsonSerialize() value, any other case its scalar
     * (a pure case its name), and any other object the public properties json_encode() reads, through any get hook.
     * A PHP array keeps its keys; an object with no string key is a stdClass.
     *
     * @return JsonData
     *
     * @throws JsonException when json_encode() cannot write the value
     */
    public function jsonValue(mixed $value): null|bool|int|float|string|array|stdClass
    {
        /** @var array<int, true> $open */
        $open = [];
        $data = $this->jsonData($value, 0, $open);

        // json_encode() holds the walked data to its exact limits: depth past 512, a non-finite float, invalid UTF-8.
        json_encode($data, JSON_THROW_ON_ERROR);

        return $data;
    }

    /**
     * The scalar an enum case serializes to: a backed case's value, a pure case's name.
     */
    public function enumScalar(UnitEnum $enum): int|string
    {
        return $enum instanceof BackedEnum ? $enum->value : $enum->name;
    }

    /**
     * Serialize a list of route arg metadata objects to a JavaScript array literal.
     *
     * Only fields that are present are emitted, so the generated TypeScript carries no `undefined` noise.
     *
     * @param  list<RouteArgData>  $args
     */
    public function routeArgsToJs(array $args): string
    {
        $entries = [];

        foreach ($args as $arg) {
            $parts = [];
            $parts[] = 'name: '.$this->toJsLiteral($arg['name']);
            $parts[] = 'required: '.$this->toJsLiteral($arg['required']);

            if (isset($arg['_routeKey'])) {
                $parts[] = '_routeKey: '.$this->toJsLiteral($arg['_routeKey']);
            }

            if (isset($arg['_enumValues'])) {
                $parts[] = '_enumValues: '.$this->toJsLiteral($arg['_enumValues']);
            }

            if (isset($arg['where'])) {
                $parts[] = 'where: '.$this->toJsLiteral($arg['where']);
            }

            $entries[] = '{'.implode(', ', $parts).'}';
        }

        return '['.implode(', ', $entries).']';
    }

    /**
     * Sanitize a string for safe inclusion in a JSDoc comment.
     *
     * Prevents premature comment termination by escaping the closing sequence.
     */
    public function sanitizeJsDoc(string $text): string
    {
        return str_replace('*/', '*\/', $text);
    }

    /**
     * Format a description string into a JSDoc comment block, every line prefixed by $indent spaces.
     *
     * Single-line descriptions render inline; multi-line ones become a ` * `-prefixed block.
     */
    public function formatJsDoc(string $description, int $indent = 0): string
    {
        $sanitized = $this->sanitizeJsDoc($description);
        $prefix = str_repeat(' ', $indent);

        if (! str_contains($sanitized, "\n")) {
            return "{$prefix}/** {$sanitized} */";
        }

        $lines = explode("\n", $sanitized);
        $result = "{$prefix}/**\n";

        foreach ($lines as $line) {
            if ($line === '') {
                $result .= "{$prefix} *\n";
            } else {
                $result .= "{$prefix} * {$line}\n";
            }
        }

        $result .= "{$prefix} */";

        return $result;
    }

    /**
     * Extract the human-readable description from a PHPDoc block,
     * ignoring all @-prefixed tags (@param, @return, @phpstan-*, etc.).
     */
    public function parseDocBlockDescription(string|false $docComment): string
    {
        if ($docComment === false || $docComment === '') {
            return '';
        }

        $lines = explode("\n", $docComment);
        $description = [];
        $inTag = false;

        foreach ($lines as $line) {
            $cleaned = preg_replace('#^\s*/?\*+/?\s?#', '', $line) ?? '';
            $cleaned = preg_replace('#\s*\*+/\s*$#', '', $cleaned) ?? '';
            $trimmed = trim($cleaned);

            // Empty remnants of /** and */
            if ($trimmed === '' || $trimmed === '/') {
                // Preserve interior blank lines only — not inside a tag block, not before any text
                if (! $inTag && $description !== []) {
                    $description[] = '';
                }
                $inTag = false;

                continue;
            }

            // An @-tag line opens a (possibly multi-line) tag block
            if (str_starts_with($trimmed, '@')) {
                $inTag = true;

                continue;
            }

            if ($inTag) {
                continue;
            }

            // Strip inline tags like {@inheritdoc}, {@see ...}, {@link ...}
            $trimmed = trim((string) preg_replace('/\s*\{@[^}]+\}\s*/', ' ', $trimmed));

            if ($trimmed === '') {
                continue;
            }

            $description[] = $trimmed;
        }

        // Trailing blank lines produced by the closing */ line
        while ($description !== [] && end($description) === '') {
            array_pop($description);
        }

        return implode("\n", $description);
    }

    /**
     * An object literal of the members, each key spelled as a JS object key.
     *
     * @param  array<array-key, mixed>  $members
     */
    private function objectLiteral(array $members): string
    {
        $pairs = [];

        foreach ($members as $key => $member) {
            $pairs[] = $this->validJsObjectKey((string) $key).': '.$this->toJsLiteral($member);
        }

        return '{'.implode(', ', $pairs).'}';
    }

    /**
     * One level of jsonValue()'s walk.
     *
     * @param  array<int, true>  $open  the objects being walked, to reject a cycle as json_encode() does
     * @return JsonData
     */
    private function jsonData(mixed $value, int $depth, array &$open): null|bool|int|float|string|array|stdClass
    {
        if ($depth > self::MAX_JSON_DEPTH) {
            throw new JsonException('Maximum stack depth exceeded', JSON_ERROR_DEPTH);
        }

        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if ($value instanceof UnitEnum && ! $value instanceof JsonSerializable) {
            return $this->enumScalar($value);
        }

        if (is_array($value)) {
            return $this->jsonContainer($this->jsonMembers($value, $depth, $open), false);
        }

        if (! is_object($value)) {
            throw new JsonException('Type is not supported', JSON_ERROR_UNSUPPORTED_TYPE);
        }

        $id = spl_object_id($value);

        if (isset($open[$id])) {
            throw new JsonException('Recursion detected', JSON_ERROR_RECURSION);
        }

        $open[$id] = true;

        try {
            if ($value instanceof JsonSerializable) {
                $serialized = $value->jsonSerialize();

                // json_encode() writes a `return $this;` as the object's own properties.
                if ($serialized !== $value) {
                    return $this->jsonData($serialized, $depth + (is_object($serialized) ? 1 : 0), $open);
                }
            }

            return $this->jsonContainer($this->jsonMembers($this->publicProperties($value), $depth, $open), true);
        } finally {
            unset($open[$id]);
        }
    }

    /**
     * Each member of an array or of an object's properties, walked one level deeper.
     *
     * @param  array<array-key, mixed>  $members
     * @param  array<int, true>  $open
     * @return array<array-key, JsonData>
     */
    private function jsonMembers(array $members, int $depth, array &$open): array
    {
        foreach ($members as $key => $member) {
            $members[$key] = $this->jsonData($member, $depth + 1, $open);
        }

        /** @var array<array-key, JsonData> $members */
        return $members;
    }

    /**
     * Walked members as json_encode() writes them; an object with no string key becomes a stdClass.
     *
     * An array keeps its keys. Only a stdClass spells such an object, `{}` among them, without printing it as a list.
     *
     * @param  array<array-key, JsonData>  $members
     * @return array<array-key, JsonData>|stdClass
     */
    private function jsonContainer(array $members, bool $isObject): array|stdClass
    {
        if (! $isObject) {
            return $members;
        }

        foreach (array_keys($members) as $key) {
            if (is_string($key)) {
                return $members;
            }
        }

        return (object) $members;
    }

    /**
     * The properties json_encode() writes for a plain object: its public, initialized ones, read through any get hook.
     *
     * get_object_vars() runs get hooks and initializes a lazy object; the array cast adds the properties internal
     * classes such as DateTime expose only to it, with every non-public key dropped.
     *
     * @return array<array-key, mixed>
     */
    private function publicProperties(object $value): array
    {
        // A closure casts to [$closure] rather than to its properties, and json_encode() writes it as {}.
        if ($value instanceof Closure) {
            return [];
        }

        return get_object_vars($value) + array_filter(
            (array) $value,
            fn (int|string $key): bool => ! is_string($key) || ! str_starts_with($key, "\0"),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
