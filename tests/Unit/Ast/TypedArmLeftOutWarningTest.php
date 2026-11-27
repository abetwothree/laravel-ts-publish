<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\DroppedArmsCorpus\UnreachedSitesResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HelperArmSelfReadResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\LeftOutNamesResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MixedNoteJoinResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedKeyLeftBesideResource;
use Workbench\App\Http\Resources\DroppedArms\ArmBesideDropResource;
use Workbench\App\Http\Resources\DroppedArms\ArmBesideTypedKeyResource;
use Workbench\App\Http\Resources\DroppedArms\WalkedArmDropResource;
use Workbench\App\Http\Resources\JoinReadingsResource;
use Workbench\App\Models\Post;

/**
 * The key types a fresh analysis of one resource publishes, beside the messages of the warnings it raises.
 *
 * @param  class-string  $class
 * @return array{array<string, string>, list<string>}
 */
function typedArmLeftOutReading(string $class): array
{
    AnalysisWarnings::reset();
    resolve(AnalysisMemo::class)->reset();
    $types = publishedResourceKeyTypes($class, Post::class);

    return [$types, array_column(AnalysisWarnings::all(), 'message')];
}

/**
 * The warning that a union left out an arm on a line beside a typed one.
 */
function typedArmLeftOutMessage(int $line, string $arm): string
{
    return "The value on line {$line} leaves out `{$arm}`, which could not be typed, so its published type may not admit "
        .'every value it sends. Give what it reads a return type or a `@return` docblock.';
}

/**
 * The warning that a join left out what a returned value on a line holds under a key, beside typed values.
 */
function returnedKeyLeftOutMessage(string $key, string $at, int $line): string
{
    return "Key `{$key}` leaves out what `{$at}` on line {$line} holds there, which could not be typed beside the typed "
        .'values it joins, so its published type may not admit every value it sends. Give what it reads a return type '
        .'or a `@return` docblock.';
}

// `cache()` sends `null` beside each typed arm, which the published type rejects, so the run says so (GH-161).
test('an arm no rule types beside a typed one warns, naming the arm, and the published type stays', function (string $class, array $types, array $warnings) {
    expect(typedArmLeftOutReading($class))->toBe([$types, $warnings]);
})->with([
    'a closure, a list of closures and same-named users, under array<string, mixed>' => [
        ArmBesideDropResource::class,
        ['cb' => '() => string', 'cbs' => '(() => string)[]', 'users' => 'WorkbenchUser[] | CrmUser[]'],
        [
            typedArmLeftOutMessage(25, "cache('cb')"),
            typedArmLeftOutMessage(26, "cache('cb')"),
            typedArmLeftOutMessage(29, "cache('u')"),
        ],
    ],
    'same-named users under a key the @return does not name' => [
        ArmBesideTypedKeyResource::class,
        ['n' => 'number', 'users' => 'WorkbenchUser[] | CrmUser[]'],
        [typedArmLeftOutMessage(27, "cache('u')")],
    ],
    'same-named users a returned variable\'s branches join' => [
        WalkedArmDropResource::class,
        ['users' => 'WorkbenchUser[] | CrmUser[]'],
        [typedArmLeftOutMessage(28, "cache('u')")],
    ],
]);

test('a spread helper\'s return whose keys no write names warns once per return, a second spread of it included', function () {
    expect(typedArmLeftOutReading(ReturnedKeyLeftBesideResource::class))->toBe([
        ['relayed' => '{ k?: string }', 'again' => '{ k?: string }', 'pair' => '{ a?: string; b?: string }'],
        [returnedKeyLeftOutMessage('k', '$row', 60), returnedKeyLeftOutMessage('a', '$row', 38)],
    ]);
});

// The literal's item names its own drop, so the other return, whose key no write names, still warns at the return.
test('a spread helper\'s return whose key no write names warns beside another return\'s untyped item', function () {
    expect(typedArmLeftOutReading(MixedNoteJoinResource::class))->toBe([
        ['mixed' => '{ a: string; b: string }'],
        [
            typedArmLeftOutMessage(32, '\\Workbench\\App\\Support\\Opaque::value($this->resource->tags)'),
            returnedKeyLeftOutMessage('b', '$row', 37),
        ],
    ]);
});

// The gap mode holds the unalike fill's warning on the gap it spells until the strip, so only the set is pinned.
test('a declared fill another return spells unalike, and a += default no rule types, each warn once, naming the key', function () {
    [$types, $warnings] = typedArmLeftOutReading(UnreachedSitesResource::class);

    expect($types)->toBe(['row' => '{ u?: User }', 'again' => '{ u?: User }', 'plus' => '{ id: number; k: string }'])
        ->and($warnings)->toEqualCanonicalizing([
            returnedKeyLeftOutMessage('u', '$declared', 34),
            returnedKeyLeftOutMessage('k', '$this->defaults()', 61),
        ]);
});

// relay()'s `$d` drops at merge-branch-join (the audit baseline's line 95), held on a gap its `@return` member fills.
test('a return\'s drop the @return types in place prints no warning', function () {
    [$types, $warnings] = typedArmLeftOutReading(JoinReadingsResource::class);

    expect($types['relays'])->toBe('{ k?: string | number[]; id: number }[]')
        ->and($warnings)->toBe([]);
});

// The walk also reads `$o['k']` under an alias no PHP variable can take; the warning names the local, once. The
// `@return` types `a`, so the return's warning names `b`, the key it leaves out.
test('a warning names the value as the source spells it, and the key the @return leaves untyped', function () {
    expect(typedArmLeftOutReading(LeftOutNamesResource::class))->toBe([
        ['own_default' => '{ k: string }', 'pair' => '{ a?: string; b?: string }'],
        [typedArmLeftOutMessage(34, "\$o['k']"), returnedKeyLeftOutMessage('b', '$row', 38)],
    ]);
});

// The walk's read of a self-read under its alias is thrown away where it drops, and the read of the source reuses the
// helper's answer, whose warning the memo then raises again.
test('a helper\'s left-out arm warns beside a self-read, whichever read reaches the helper first', function () {
    expect(typedArmLeftOutReading(HelperArmSelfReadResource::class))->toBe([
        [
            'coalesced' => '{ id: number; k: string }',
            'spread_self' => '{ id: number; k: string }',
            'key_reads_other' => '{ id: number; k: string }',
            'read_first' => 'string',
            'reread' => '{ id: number; k: string }',
        ],
        [
            typedArmLeftOutMessage(85, "cache('fallback')"),
            typedArmLeftOutMessage(42, "\$d['k']"),
            typedArmLeftOutMessage(93, "cache('picked')"),
            typedArmLeftOutMessage(101, "cache('chosen')"),
            typedArmLeftOutMessage(109, "cache('first')"),
            typedArmLeftOutMessage(75, "\$d['k']"),
        ],
    ]);
});
