<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\ReturnLiteralReader;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReturnLiteralProbe;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\SubjectProps;
use Workbench\App\Events\ServerCreated;

it('reads a method that returns one whole string literal', function () {
    expect(resolve(ReturnLiteralReader::class)->stringLiteral(ServerCreated::class, 'broadcastAs'))
        ->toBe('server.created');
});

// A reader that took the first literal of `'order.'.$this->kind` would ship "order." as the Echo key.
it('returns null for a concatenated return rather than folding it to its literal prefix', function () {
    expect(resolve(ReturnLiteralReader::class)->stringLiteral(SubjectProps::class, 'computedName'))
        ->toBeNull();
});

it('returns null for a method with no return statement', function () {
    expect(resolve(ReturnLiteralReader::class)->stringLiteral(SubjectProps::class, 'noReturn'))
        ->toBeNull();
});

it('returns null for a method that does not exist', function () {
    expect(resolve(ReturnLiteralReader::class)->stringLiteral(ServerCreated::class, 'notAMethod'))
        ->toBeNull();
});

// A closure's `return` is not the method's; counting it over-rejects a perfectly whole literal.
it('ignores returns that belong to a nested closure', function () {
    expect(resolve(ReturnLiteralReader::class)->stringLiteral(SubjectProps::class, 'literalPastAClosure'))
        ->toBe('past.the.closure');
});

it('returns null for a method whose returns spell different literals', function () {
    expect(resolve(ReturnLiteralReader::class)->stringLiteral(SubjectProps::class, 'twoLiteralReturns'))
        ->toBeNull();
});

it('reads a literal every return spells alike', function () {
    expect(resolve(ReturnLiteralReader::class)->stringLiteral(ReturnLiteralProbe::class, 'sameLiteralTwice'))
        ->toBe('same');
});

// A generator's call returns the Generator, never the value its `return` spells.
it('returns null for a generator', function () {
    expect(resolve(ReturnLiteralReader::class)->stringLiteral(ReturnLiteralProbe::class, 'generatedName'))
        ->toBeNull();
});

it('reads the list of string literals every return spells', function (string $method, array $list) {
    expect(resolve(ReturnLiteralReader::class)->strings(ReturnLiteralProbe::class, $method))->toBe($list);
})->with([
    'one list' => ['columns', ['id', 'uuid']],
    'the same list on every branch' => ['sameColumnsTwice', ['id']],
    'an empty list' => ['noColumns', []],
]);

it('returns null for a list that is not the same keyless list of string literals on every return', function (string $method) {
    expect(resolve(ReturnLiteralReader::class)->strings(ReturnLiteralProbe::class, $method))->toBeNull();
})->with([
    'lists that differ by branch' => ['differentColumns'],
    'a keyed item' => ['keyedColumns'],
    'a spread' => ['spreadColumns'],
    'a non-string item' => ['mixedColumns'],
    'a list built at runtime' => ['computedColumns'],
    'a string' => ['sameLiteralTwice'],
    'a method that does not exist' => ['notAMethod'],
]);

it('returns null for a list where a string is asked', function () {
    expect(resolve(ReturnLiteralReader::class)->stringLiteral(ReturnLiteralProbe::class, 'columns'))->toBeNull();
});

// MethodLocator memoizes the parse, so a warm read records no file unless the reader records the class itself.
it('records the class whose method it reads, on a warm read too', function () {
    resolve(ReturnLiteralReader::class)->strings(ReturnLiteralProbe::class, 'columns');
    DependencyRecorder::start();
    resolve(ReturnLiteralReader::class)->strings(ReturnLiteralProbe::class, 'columns');
    $paths = DependencyRecorder::paths();
    DependencyRecorder::stop();

    expect($paths)->toContain((string) new ReflectionClass(ReturnLiteralProbe::class)->getFileName());
});
