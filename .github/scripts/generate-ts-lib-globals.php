<?php

declare(strict_types=1);

/*
 * Writes src/Support/TypeStrings/TsLibGlobals.php: every global name the TypeScript lib files this repository's
 * tsconfig*.json files load declare at the top level, read from the installed TypeScript, each type apart from each
 * name declared only as a value.
 *
 * Usage: php .github/scripts/generate-ts-lib-globals.php [--stdout]
 *        --stdout prints the file instead of writing it; TsLibGlobalsTest compares the two.
 */

$root = dirname(__DIR__, 2);
$target = $root.'/src/Support/TypeStrings/TsLibGlobals.php';
$libDirectory = tsLibDirectory($root);

if ($libDirectory === null) {
    fwrite(STDERR, "No TypeScript lib directory under node_modules; run npm install first.\n");
    exit(1);
}

$roots = tsconfigRootLibs($root);
$types = [];
$values = [];

foreach (referencedLibs($libDirectory, $roots) as $file) {
    foreach (globalDeclarations((string) file_get_contents($file)) as [$keyword, $name]) {
        if (in_array($keyword, ['var', 'let', 'const', 'function'], true)) {
            $values[$name] = true;
        } else {
            $types[$name] = true;
        }
    }
}

$types = array_keys($types);
$values = array_keys(array_diff_key($values, array_flip($types)));
sort($types, SORT_STRING);
sort($values, SORT_STRING);
$content = render($types, $values, $roots);

if (in_array('--stdout', $argv, true)) {
    echo $content;
} else {
    file_put_contents($target, $content);
    echo count($types).' types and '.count($values)." values written to {$target}\n";
}

/**
 * The installed TypeScript's lib directory: a native build keeps it in its platform package.
 */
function tsLibDirectory(string $root): ?string
{
    $candidates = [
        ...glob($root.'/node_modules/@typescript/typescript-*/lib/lib.es5.d.ts') ?: [],
        $root.'/node_modules/typescript/lib/lib.es5.d.ts',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return dirname($candidate);
        }
    }

    return null;
}

/**
 * The libs each tsconfig*.json loads: its `lib`, or else the default lib of its `target`, following `extends`.
 *
 * @return list<string>
 */
function tsconfigRootLibs(string $root): array
{
    $libs = [];

    foreach (glob($root.'/tsconfig*.json') ?: [] as $file) {
        $options = compilerOptions($file);

        if (isset($options['lib']) && is_array($options['lib'])) {
            foreach ($options['lib'] as $lib) {
                $libs[] = strtolower((string) $lib);
            }

            continue;
        }

        $target = strtolower((string) ($options['target'] ?? ''));

        $libs[] = match (true) {
            $target === '' => throw new RuntimeException("{$file} sets neither `lib` nor `target`"),
            in_array($target, ['es3', 'es5'], true) => '',
            in_array($target, ['es6', 'es2015'], true) => 'es6',
            default => $target.'.full',
        };
    }

    return array_values(array_unique($libs));
}

/**
 * A tsconfig's compiler options merged over those of the file it extends.
 *
 * @return array<string, mixed>
 */
function compilerOptions(string $file): array
{
    // tsconfig allows comments and trailing commas; a string is kept whole so a `//` inside it survives.
    $json = (string) preg_replace_callback(
        '#"(?:\\\\.|[^"\\\\])*"|//[^\n]*|/\*.*?\*/#s',
        static fn (array $match): string => $match[0][0] === '"' ? $match[0] : '',
        (string) file_get_contents($file),
    );
    $config = json_decode((string) preg_replace('/,(\s*[}\]])/', '$1', $json), true, flags: JSON_THROW_ON_ERROR);
    $options = is_array($config) && is_array($config['compilerOptions'] ?? null) ? $config['compilerOptions'] : [];
    $parent = is_array($config) && is_string($config['extends'] ?? null) ? $config['extends'] : null;

    return $parent === null ? $options : [...compilerOptions(dirname($file).'/'.$parent), ...$options];
}

/**
 * Every lib file the root libs load, following each `/// <reference lib="…" />`.
 *
 * @param  list<string>  $roots
 * @return list<string>
 */
function referencedLibs(string $directory, array $roots): array
{
    $files = [];
    $queue = $roots;

    while ($queue !== []) {
        $lib = array_shift($queue);
        $file = $directory.'/'.($lib === '' ? 'lib.d.ts' : "lib.{$lib}.d.ts");

        if (isset($files[$file])) {
            continue;
        }

        $files[$file] = true;
        preg_match_all('#^/// <reference lib="([^"]+)" />#m', (string) file_get_contents($file), $references);
        array_push($queue, ...array_map('strtolower', $references[1]));
    }

    return array_keys($files);
}

/**
 * The global declarations one lib file makes, each its keyword beside its name: its top-level declarations, or a
 * module's `declare global` members.
 *
 * @return list<array{string, string}>
 */
function globalDeclarations(string $source): array
{
    $declaration = '(?:declare\s+)?(?:abstract\s+)?(interface|type|var|let|const|class|namespace|function|enum)\s+'
        .'([A-Za-z_$][\w$]*)';

    if (preg_match('/^export \{\};$/m', $source) === 1) {
        preg_match_all('/^declare global \{\n(.*?)^\}/ms', $source, $blocks);
        preg_match_all('/^    '.$declaration.'/m', implode("\n", $blocks[1]), $matches, PREG_SET_ORDER);
    } else {
        preg_match_all('/^'.$declaration.'/m', $source, $matches, PREG_SET_ORDER);
    }

    return array_map(static fn (array $match): array => [$match[1], $match[2]], $matches);
}

/**
 * The generated class.
 *
 * @param  list<string>  $types
 * @param  list<string>  $values
 * @param  list<string>  $roots
 */
function render(array $types, array $values, array $roots): string
{
    $libs = implode(', ', array_map(static fn (string $lib): string => $lib === '' ? 'lib' : $lib, $roots));
    $typeEntries = entries($types);
    $valueEntries = entries($values);

    return <<<PHP
        <?php

        declare(strict_types=1);

        namespace AbeTwoThree\\LaravelTsPublish\\Support\\TypeStrings;

        /**
         * Every global name the TypeScript libs this repository's tsconfig files load ({$libs}) declare at the
         * top level, which a type spells with no import: a type, or after `typeof` a value too. Generated by
         * .github/scripts/generate-ts-lib-globals.php: do not edit.
         *
         * @internal
         */
        final class TsLibGlobals
        {
            /** @var array<string, bool> name => true: each interface, type alias, class, enum and namespace */
            public const array TYPES = [
        {$typeEntries}
            ];

            /** @var array<string, bool> name => true: each name only a `var`, `let`, `const` or `function` declares */
            public const array VALUES = [
        {$valueEntries}
            ];
        }

        PHP;
}

/**
 * The names as array entries, as many to a line as fit in 120 characters.
 *
 * @param  list<string>  $names
 */
function entries(array $names): string
{
    $lines = [];
    $line = '       ';

    foreach ($names as $name) {
        $entry = " '{$name}' => true,";

        if (strlen($line) + strlen($entry) > 120) {
            $lines[] = $line;
            $line = '       ';
        }

        $line .= $entry;
    }

    $lines[] = $line;

    return implode("\n", $lines);
}
