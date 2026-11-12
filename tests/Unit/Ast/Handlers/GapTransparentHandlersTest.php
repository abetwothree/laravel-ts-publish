<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;

/**
 * The type a value template reads as, its `%s` the value `gapped()` holds, through PostResource over a Post.
 */
function gapTransparentType(string $php, string $held): string
{
    return gappedValue(sprintf($php, "gapped('".$held."')"), PostResource::class, Post::class)['type'];
}

// Each handler decides as on the value with its gap arms dropped, and gives the gaps back to what it builds.
it('reads a value holding a gap arm as without it, and carries the gap into what it builds', function (string $php, string $held) {
    $plain = gapTransparentType($php, $held);
    $gapped = gapTransparentType($php, $held.' | $gap9');

    expect($plain)->not->toBe('unknown')
        ->and(VisibleGaps::stripped($gapped)[0])->toBe($plain)
        ->and(VisibleGaps::ids($gapped))->toBe([9]);
})->with([
    'array_map() of a list (A1)' => ['array_map(fn ($row) => [$row], %s)', 'string[]'],
    'collect()->map()->all() of a list (A1)' => ['collect(%s)->map(fn ($row) => [$row])->all()', 'string[]'],
    'array_filter() of a list (A8, O2)' => ['array_filter(%s)', '(string | null)[]'],
    'array_values() of a Record (A8, O2)' => ['array_values(%s)', 'Record<string, number>'],
    'array_keys() of a Record (A8, O2)' => ['array_keys(%s)', 'Record<string, number>'],
    'array_map() of two lists, as an element (O2)' => ['array_map(null, %s, [1])', 'string[]'],
    '(object) of a Record (O3)' => ['(object) %s', 'Record<string, number>'],
    '(array) of a list (O3)' => ['(array) %s', 'string[]'],
    'collect() of a list (O4)' => ['collect(%s)->all()', 'string[]'],
    'a key read off an object (N1)' => ["%s['name']", '{ name: string }'],
]);

it('reads an operand holding a gap arm as the scalar it is beside the gap (O5)', function () {
    expect(gapTransparentType('%s + 1', 'number | $gap9'))->toBe(gapTransparentType('%s + 1', 'number'));
});

it('reads a key off an object beside a gap as the member beside that gap', function () {
    expect(gapTransparentType("%s['name']", '{ name: string } | $gap1'))->toBe('string | $gap1');
});

// A spread's own gap arm names no key and opens nothing (ruling E2), so the literal reads as with it dropped.
it('reads a literal spreading a value that holds a gap arm or the open marker as with neither', function (string $php, string $held, string $marked) {
    $plain = gapTransparentType($php, $held);

    expect($plain)->not->toBe('unknown')
        ->and(VisibleGaps::stripped(gapTransparentType($php, $held.' | $gap9'))[0])->toBe($plain)
        ->and(VisibleGaps::stripped(gapTransparentType($php, $marked))[0])->toBe(gapTransparentType($php, VisibleGaps::stripped($marked)[0]));
})->with([
    'a value spread before a key' => ["[...%s, 'k' => 1]", '{ a: string }', '{ a: string; #$open: unknown }'],
    'a value spread after a key' => ["['k' => 1, ...%s]", '{ a: string; k?: number }', '{ a: string; k?: number; #$open: unknown }'],
    'a map spread after a key' => ["['k' => 1, ...%s]", 'Record<string, number>', '{ [key: string]: number; #$open: unknown }'],
    'a union of objects spread' => ["['k' => 1, ...%s]", '{ a: string } | { b: number }', '{ a: string; #$open: unknown } | { b: number }'],
]);
