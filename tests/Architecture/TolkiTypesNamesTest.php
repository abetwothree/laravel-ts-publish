<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\TolkiTypes;
use Symfony\Component\Finder\Finder;

/**
 * Every name TolkiTypes holds for `@tolki/types`: each string its constants map to, and the module specifier.
 *
 * @return list<string>
 */
function tolkiTypesNames(): array
{
    $constants = new ReflectionClass(TolkiTypes::class)->getConstants();
    $names = [];

    array_walk_recursive($constants, function (mixed $value) use (&$names): void {
        if (is_string($value)) {
            $names[] = $value;
        }
    });

    return array_values(array_unique($names));
}

/**
 * The pattern that finds one of the names as its own token: a name after a backslash is a PHP class name's segment.
 *
 * @param  list<string>  $names
 */
function tolkiNamePattern(array $names): string
{
    return '#(?<![\w\\\\$@/-])('.implode('|', array_map(fn (string $name): string => preg_quote($name, '#'), $names)).')(?![\w/-])#';
}

/**
 * Each place a file in `src/` spells one of the names inside a string literal, as `file:line name`.
 *
 * @param  list<string>  $names
 * @return list<string>
 */
function handSpelledTolkiNames(array $names): array
{
    $pattern = tolkiNamePattern($names);
    $found = [];

    foreach (Finder::create()->files()->in(dirname(__DIR__, 2).'/src')->name('*.php')->notPath('Support/TolkiTypes.php') as $file) {
        foreach (PhpToken::tokenize($file->getContents()) as $token) {
            if ($token->is([T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE])
                && preg_match_all($pattern, $token->text, $matches) > 0) {
                foreach ($matches[1] as $name) {
                    $found[] = $file->getRelativePathname().':'.$token->line.' '.$name;
                }
            }
        }
    }

    sort($found);

    return $found;
}

// One table names each @tolki/types type, so a rename there reaches every published file; a spelled name is missed.
test('no file in src/ spells a name TolkiTypes holds for @tolki/types', function () {
    expect(handSpelledTolkiNames(tolkiTypesNames()))->toBe([]);
});

test('the scan reads a @tolki/types name inside a type string, never inside a PHP class name', function (string $source, int $found) {
    expect(tolkiTypesNames())->toContain('AnonymousResourceCollection')->toContain('@tolki/types')
        ->and(preg_match_all(tolkiNamePattern(tolkiTypesNames()), $source))->toBe($found);
})->with([
    'a generic type string' => ['"AnonymousResourceCollection<{$name}>"', 1],
    'the module specifier' => ["['@tolki/types' => ['JsonApiDocument']]", 2],
    'a PHP class name' => ["'Illuminate\\Http\\Resources\\Json\\AnonymousResourceCollection'", 0],
    'a longer name' => ["'MyAnonymousResourceCollection'", 0],
]);
