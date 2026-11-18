<?php

declare(strict_types=1);

/*
 * Writes src/Support/TypeStrings/TsLibGlobals.php: every global name the TypeScript lib files this repository's
 * tsconfig*.json files load declare at the top level, read from the installed TypeScript.
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
$names = [];

foreach (referencedLibs($libDirectory, $roots) as $file) {
    array_push($names, ...globalNames((string) file_get_contents($file)));
}

$names = array_values(array_unique($names));
sort($names, SORT_STRING);
$content = render($names, $roots);

if (in_array('--stdout', $argv, true)) {
    echo $content;
} else {
    file_put_contents($target, $content);
    echo count($names)." names written to {$target}\n";
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
 * The global names one lib file declares: its top-level declarations, or a module's `declare global` members.
 *
 * @return list<string>
 */
function globalNames(string $source): array
{
    $declaration = '(?:declare\s+)?(?:abstract\s+)?(?:interface|type|var|let|const|class|namespace|function|enum)\s+'
        .'([A-Za-z_$][\w$]*)';

    if (preg_match('/^export \{\};$/m', $source) === 1) {
        preg_match_all('/^declare global \{\n(.*?)^\}/ms', $source, $blocks);
        preg_match_all('/^    '.$declaration.'/m', implode("\n", $blocks[1]), $matches);
    } else {
        preg_match_all('/^'.$declaration.'/m', $source, $matches);
    }

    return $matches[1];
}

/**
 * The generated class.
 *
 * @param  list<string>  $names
 * @param  list<string>  $roots
 */
function render(array $names, array $roots): string
{
    $libs = implode(', ', array_map(static fn (string $lib): string => $lib === '' ? 'lib' : $lib, $roots));
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
    $entries = implode("\n", $lines);

    return <<<PHP
        <?php

        declare(strict_types=1);

        namespace AbeTwoThree\\LaravelTsPublish\\Support\\TypeStrings;

        /**
         * Every global name the TypeScript libs this repository's tsconfig files load ({$libs}) declare at the
         * top level, which a type spells with no import. Generated by .github/scripts/generate-ts-lib-globals.php:
         * do not edit.
         *
         * @internal
         */
        final class TsLibGlobals
        {
            /** @var array<string, bool> name => true */
            public const array NAMES = [
        {$entries}
            ];
        }

        PHP;
}
