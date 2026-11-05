<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Docblocks;

use AbeTwoThree\LaravelTsPublish\Ast\Receivers\DeclaredClasses;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\ScanDialect;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\TypeScanner;
use ReflectionClass;

/**
 * The `@phpstan-type` and `@psalm-type` aliases a class sees: its own definitions first, then each
 * `@phpstan-import-type Name from Class [as Alias]`, followed to the class that wrote the definition.
 *
 * @phpstan-type TypeAlias array{definition: string, class: class-string}
 *
 * @internal
 */
final class TypeAliases
{
    /**
     * Each class's answers, alias name => definition, or null where the class sees no such alias.
     *
     * @var array<string, array<string, TypeAlias|null>>
     */
    private static array $answers = [];

    /**
     * The files each cached answer read, replayed on a reuse; null when the answer was cached while nothing recorded.
     *
     * @var array<string, array<string, list<string>|null>>
     */
    private static array $paths = [];

    /**
     * The alias a name spells in a class's docblock, with the class whose docblock defines it; null when it is none.
     *
     * @param  ReflectionClass<object>  $context
     * @return TypeAlias|null
     */
    public static function find(string $name, ReflectionClass $context): ?array
    {
        return self::lookup($name, $context, []);
    }

    /**
     * The alias find() reads, null where its own or any import's "{FQCN}::{alias}" lookup is already under way.
     *
     * @param  ReflectionClass<object>  $context
     * @param  array<string, true>  $seen
     * @return TypeAlias|null
     */
    public static function lookup(string $name, ReflectionClass $context, array $seen): ?array
    {
        $key = $context->getName();
        $seenKey = $key.'::'.$name;

        if (isset($seen[$seenKey])) {
            return null;
        }

        $seen[$seenKey] = true;

        // A reuse replays the class files the answer read, so a later class's cache entry still names them.
        if (array_key_exists($name, self::$answers[$key] ?? [])
            && ((self::$paths[$key][$name] ?? null) !== null || ! DependencyRecorder::isRecording())) {
            foreach (self::$paths[$key][$name] ?? [] as $path) {
                DependencyRecorder::record($path);
            }

            return self::$answers[$key][$name];
        }

        $mark = DependencyRecorder::mark();
        // The class defines the alias or imports it, and each class an import names is read the same way below.
        DependencyRecorder::record((string) $context->getFileName());
        $doc = $context->getDocComment();
        $resolved = $doc === false ? null : self::defined($name, $context, $doc) ?? self::imported($name, $context, $doc, $seen);

        self::$paths[$key][$name] = DependencyRecorder::isRecording() ? DependencyRecorder::since($mark) : null;

        return self::$answers[$key][$name] = $resolved;
    }

    /**
     * Whether a name is a PHPDoc identifier, as PHPStan's lexer reads one, so it may name an alias.
     */
    public static function isName(string $name): bool
    {
        // A hyphen or a non-ASCII byte may follow the first character.
        return preg_match('/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff-]*$/', $name) === 1;
    }

    /**
     * Forget every answer, so a run reads each class's docblock as it is now.
     */
    public static function flush(): void
    {
        self::$answers = [];
        self::$paths = [];
    }

    /**
     * Every cached answer, class => alias name => definition or null.
     *
     * @return array<string, array<string, TypeAlias|null>>
     */
    public static function answers(): array
    {
        return self::$answers;
    }

    /**
     * The files each cached answer read, class => alias name => paths, or null where nothing recorded.
     *
     * @return array<string, array<string, list<string>|null>>
     */
    public static function answerPaths(): array
    {
        return self::$paths;
    }

    /**
     * Extend a single-line `@phpstan-type`/`@psalm-type` capture across further docblock lines while a bracket it
     * opens stays open — a multi-line `array{...}` shape — stripping each line's leading `*` comment marker.
     */
    public static function balance(string $firstLine, string $doc, int $offsetAfterFirstLine): string
    {
        $definition = trim(rtrim($firstLine, " \t*/"));

        if (self::depth($definition) <= 0) {
            return $definition;
        }

        foreach (explode("\n", substr($doc, $offsetAfterFirstLine)) as $line) {
            $stripped = trim((string) preg_replace('#^\s*\*+/?\s?#', '', $line));

            if ($stripped === '') {
                continue;
            }

            $definition .= ' '.rtrim($stripped, " \t*/");

            if (self::depth($definition) <= 0) {
                break;
            }
        }

        return trim($definition);
    }

    /**
     * The alias a class's own `@phpstan-type` or `@psalm-type` defines under a name, or null.
     *
     * @param  ReflectionClass<object>  $context
     * @return TypeAlias|null
     */
    private static function defined(string $name, ReflectionClass $context, string $doc): ?array
    {
        if (! preg_match(
            '/@(?:phpstan|psalm)-type\s+'.preg_quote($name, '/').'\b(?=[\s=])\s*=?\s*(\S.*)$/m',
            $doc,
            $m,
            PREG_OFFSET_CAPTURE,
        )) {
            return null;
        }

        return ['definition' => self::balance($m[1][0], $doc, $m[1][1] + strlen($m[1][0])), 'class' => $context->getName()];
    }

    /**
     * The alias a class's first `@phpstan-import-type` or `@psalm-import-type` binding a name imports, or null.
     *
     * @param  ReflectionClass<object>  $context
     * @param  array<string, true>  $seen
     * @return TypeAlias|null
     */
    private static function imported(string $name, ReflectionClass $context, string $doc, array $seen): ?array
    {
        if (! preg_match_all(
            '/@(?:phpstan|psalm)-import-type\s+(\w+)\s+from\s+([\w\\\\]+)(?:\s+as\s+(\w+))?/m',
            $doc,
            $imports,
            PREG_SET_ORDER,
        )) {
            return null;
        }

        foreach ($imports as $import) {
            if (($import[3] ?? $import[1]) !== $name) {
                continue;
            }

            $sourceClass = DocblockNames::resolve($import[2], $context);

            return DeclaredClasses::isClassLike($sourceClass)
                ? self::lookup($import[1], new ReflectionClass($sourceClass), $seen)
                : null;
        }

        return null;
    }

    /**
     * The bracket depth a PHPDoc type leaves open at its end, quoted literals skipped, as the type scanner reads it.
     */
    private static function depth(string $s): int
    {
        // A PHPDoc literal never runs past its line, so a space after the type is always read, at the depth left open.
        return TypeScanner::depths($s.' ', ScanDialect::PhpDoc)[strlen($s)] ?? 0;
    }
}
