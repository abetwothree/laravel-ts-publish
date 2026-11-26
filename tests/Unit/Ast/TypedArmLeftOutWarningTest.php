<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\DroppedArmsCorpus\UnalikeFillDropResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\LeftOutNamesResource;
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

// Box 5 (GH-161): `cache()` sends `null` beside each typed arm, which the published type rejects, so the run says so.
test('an arm no rule types beside a typed one warns, naming the arm, and the published type stays', function (string $class, array $types, array $warnings) {
    expect(typedArmLeftOutReading($class))->toBe([$types, $warnings]);
})->with([
    'T8A1, T8A3 and T8B1 under array<string, mixed>' => [
        ArmBesideDropResource::class,
        ['cb' => '() => string', 'cbs' => '(() => string)[]', 'users' => 'WorkbenchUser[] | CrmUser[]'],
        [
            typedArmLeftOutMessage(25, "cache('cb')"),
            typedArmLeftOutMessage(26, "cache('cb')"),
            typedArmLeftOutMessage(29, "cache('u')"),
        ],
    ],
    'T8B3, a key the @return does not name' => [
        ArmBesideTypedKeyResource::class,
        ['n' => 'number', 'users' => 'WorkbenchUser[] | CrmUser[]'],
        [typedArmLeftOutMessage(27, "cache('u')")],
    ],
    'T8B2, a returned variable\'s branches' => [
        WalkedArmDropResource::class,
        ['users' => 'WorkbenchUser[] | CrmUser[]'],
        [typedArmLeftOutMessage(28, "cache('u')")],
    ],
]);

test('a spread helper\'s return whose keys no write names warns once per return, a second spread of it included', function () {
    expect(typedArmLeftOutReading(ReturnedKeyLeftBesideResource::class))->toBe([
        ['relayed' => '{ k?: string }', 'again' => '{ k?: string }', 'pair' => '{ a?: string; b?: string }'],
        [returnedKeyLeftOutMessage('k', '$row', 37), returnedKeyLeftOutMessage('a', '$row', 51)],
    ]);
});

test('a declared fill another return spells unalike warns once, naming the key and the return', function () {
    expect(typedArmLeftOutReading(UnalikeFillDropResource::class))->toBe([
        ['row' => '{ u?: User }', 'again' => '{ u?: User }'],
        [returnedKeyLeftOutMessage('u', '$declared', 33)],
    ]);
});

// relay()'s `$d` drops at merge-branch-join (the audit baseline's line 95), held on a gap its `@return` member fills.
test('a return\'s drop the @return types in place prints no warning', function () {
    [$types, $warnings] = typedArmLeftOutReading(JoinReadingsResource::class);

    expect($types['relays'])->toBe('{ k?: string | number[]; id: number }[]')
        ->and($warnings)->toBe([]);
});

// The walk also reads `$o['k']` under an alias no PHP variable can take; the warning names the local, once. The `@return`
// types `a`, so the return's warning names `b`, the key it leaves out.
test('a warning names the value as the source spells it, and the key the @return leaves untyped', function () {
    expect(typedArmLeftOutReading(LeftOutNamesResource::class))->toBe([
        ['own_default' => '{ k: string }', 'pair' => '{ a?: string; b?: string }'],
        [typedArmLeftOutMessage(32, "\$o['k']"), returnedKeyLeftOutMessage('b', '$row', 45)],
    ]);
});
