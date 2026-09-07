<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

use BackedEnum;
use InvalidArgumentException;
use stdClass;
use UnitEnum;

/**
 * Turn PHP values and docblock text into JavaScript source: object keys, identifiers, literals,
 * route-argument objects, and JSDoc blocks.
 *
 * @internal
 */
class JsEmitter
{
    /** @var list<string> */
    private const array RESERVED_JS_IDENTIFIERS = [
        'break', 'case', 'catch', 'class', 'const', 'continue', 'debugger',
        'default', 'delete', 'do', 'else', 'export', 'extends', 'false',
        'finally', 'for', 'function', 'if', 'import', 'in', 'instanceof',
        'let', 'new', 'null', 'return', 'static', 'super', 'switch', 'this',
        'throw', 'true', 'try', 'typeof', 'var', 'void', 'while', 'with',
        'yield',
    ];

    /**
     * $allowIndexSignature: a generated `[key: number]`/`[key: string]` is valid TS only in a type
     * position — pass true only there. In a value position (an object literal) it's a syntax error,
     * so every other caller must keep the default and never risk emitting it unquoted.
     */
    public function validJsObjectKey(string $key, bool $allowIndexSignature = false): string
    {
        if (preg_match('/^[a-zA-Z_$][a-zA-Z0-9_$]*$/', $key)
            || ($allowIndexSignature && preg_match('/^\[[a-zA-Z_$][a-zA-Z0-9_$]*: (?:string|number)\]$/', $key))) {
            return $key;
        }

        // json_encode produces a properly escaped double-quoted string valid in JS/TS
        return (string) json_encode($key);
    }

    /**
     * Ensure a string is safe as a bare JS/TS identifier ('delete' → 'deleteMethod').
     *
     * Not for object property keys — reserved words are legal there in TS interfaces and literals.
     *
     * @param  string  $name  The proposed identifier
     * @param  string  $suffix  Required suffix appended when $name is reserved (e.g., 'Method', 'Controller')
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

        if ($value instanceof stdClass && get_object_vars($value) === []) {
            return '{}';
        }

        if (is_object($value)) {
            $value = (array) $value;
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                return '['.implode(', ', array_map(fn ($v) => $this->toJsLiteral($v), $value)).']';
            }

            $pairs = [];
            foreach ($value as $key => $val) {
                $pairs[] = $this->validJsObjectKey((string) $key).': '.$this->toJsLiteral($val);
            }

            return '{'.implode(', ', $pairs).'}';
        }

        return 'null';
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
     * @param  list<array{name: string, required: bool, _routeKey?: string, _enumValues?: list<string|int>, where?: string}>  $args
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
     * Format a description string into a JSDoc comment block.
     *
     * Single-line descriptions render inline; multi-line ones become a ` * `-prefixed block.
     *
     * @param  int  $indent  Number of leading spaces to prefix every line of the output.
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
}
