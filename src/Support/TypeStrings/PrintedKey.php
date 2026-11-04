<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support\TypeStrings;

use AbeTwoThree\LaravelTsPublish\Support\PhpArrayKey;

/**
 * The key a printed object-literal key names, the inverse of JsEmitter::validJsObjectKey(): `name`, `0`, `"a\/b"` or
 * `'it\'s'`, each optionally marked `?`. A key is compared by the name it decodes to, never by its spelling.
 *
 * @internal
 */
final class PrintedKey
{
    /** A bare identifier as TypeScript reads one, in the categories Unicode's ID_Start and ID_Continue are made of. */
    private const string IDENTIFIER = '/^[\p{L}\p{Nl}$_][\p{L}\p{Nl}\p{Mn}\p{Mc}\p{Nd}\p{Pc}$\x{200C}\x{200D}]*$/u';

    /** One string literal, whole: its quote appears inside it only escaped. */
    private const string QUOTED = '/^(["\'])((?:(?!\1)[^\\\\]|\\\\.)*)\1$/s';

    /**
     * The key a printed key names, its optional marker dropped: a bare identifier or list index as written, a quoted
     * key unescaped; null for an index signature or any other text.
     */
    public static function decode(string $printed): ?string
    {
        $key = str_ends_with($printed, '?') ? rtrim(substr($printed, 0, -1)) : $printed;

        if (preg_match(self::IDENTIFIER, $key) === 1 || PhpArrayKey::listIndex($key) !== null) {
            return $key;
        }

        return preg_match(self::QUOTED, $key, $quoted) === 1 ? self::unescaped($quoted[2]) : null;
    }

    /**
     * Whether a printed key names this key.
     */
    public static function names(string $printed, string $key): bool
    {
        return self::decode($printed) === $key;
    }

    /**
     * A quoted key's text as JavaScript reads it, or null for an escape JSON lacks, which no printer here writes.
     */
    private static function unescaped(string $text): ?string
    {
        // JSON reads every escape the printers write; only `\'`, a bare `"` and a raw control character are respelled.
        $json = preg_replace_callback('/\\\\.|["\x00-\x1f]/s', static fn (array $match): string => match ($match[0]) {
            "\\'" => "'",
            '"' => '\\"',
            default => strlen($match[0]) === 1 ? sprintf('\\u%04x', ord($match[0])) : $match[0],
        }, $text);
        $decoded = $json === null ? null : json_decode('"'.$json.'"');

        return is_string($decoded) ? $decoded : null;
    }
}
