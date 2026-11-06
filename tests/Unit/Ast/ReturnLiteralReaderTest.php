<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Parsing\ReturnLiteralReader;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\LiteralNullReturns;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReturnLiteralProbe;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\StoppingChild;
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

// A cast whose serialize() declares its type takes get()'s null only from what get() itself returns, and an end no
// branch can reach returns nothing.
it('tells a method\'s own literal null return from a null only a value it reads may hold', function (string $method, bool $literal) {
    expect(resolve(ReturnLiteralReader::class)->returnsNull(LiteralNullReturns::class, $method))->toBe($literal);
})->with([
    'return null;' => ['returnsNull', true],
    'a bare return' => ['returnsNothing', true],
    'a fall-off' => ['fallsOff', true],
    'a ternary arm' => ['ternaryArm', true],
    'a short ternary arm' => ['shortTernaryArm', true],
    'a nested ternary arm' => ['nestedTernaryArm', true],
    'a match arm' => ['matchArm', true],
    'the right of ??' => ['coalesceArm', true],
    'a nullable property' => ['readsProperty', false],
    'a helper returning null' => ['callsHelper', false],
    'a closure returning null' => ['closureReturnsNull', false],
    'a string right of ??' => ['coalesceDefault', false],
    'a declared mixed that throws' => ['declaredMixed', false],
    'a method no class declares' => ['missing', false],
    'an arrow function returning null' => ['arrowFunctionNull', false],
    'a null default parameter' => ['nullDefault', false],
    'a null in a ternary condition' => ['nullCondition', false],
    'an if/elseif/else whose every arm returns or throws' => ['ifElseAllReturn', false],
    'a switch with a default whose every case returns' => ['switchAllReturn', false],
    'a try and a catch that both return' => ['tryAllReturn', false],
    'a finally that returns' => ['finallyReturns', false],
    'a switch with no default' => ['switchWithoutDefault', true],
    'a switch a break leaves' => ['switchBreaks', true],
    'a catch that ends without a value' => ['catchFallsOff', true],
    'an end past abort(), which never returns' => ['abortsAtEnd', false],
    'an end past abort_if(), which may return' => ['abortIfAtEnd', true],
    'an end past its own method declaring never' => ['endsInOwnNeverMethod', false],
    'an end past its own static method documenting never' => ['endsInDocumentedNeverMethod', false],
    'an end past a match whose every arm throws' => ['endsInThrowingMatch', false],
    'an end past a match one of whose arms is a value' => ['endsInValueMatch', true],
    'a while (true) only a return leaves' => ['loopsUntilItReturns', false],
    'a for (;;) only a return leaves, past a continue' => ['loopsForeverUntilItReturns', false],
    'a while (true) a break leaves' => ['loopsUntilItBreaks', true],
    'a while (true) a break 2 in a switch leaves' => ['loopsUntilASwitchBreaksOut', true],
    'a do-while whose body always returns' => ['doWhileReturns', false],
    'a do-while whose body may run to its end' => ['doWhileFinishes', true],
]);

it('records the class whose method it reads for a literal null, on a warm read too', function () {
    resolve(ReturnLiteralReader::class)->returnsNull(LiteralNullReturns::class, 'returnsNull');
    DependencyRecorder::start();
    resolve(ReturnLiteralReader::class)->returnsNull(LiteralNullReturns::class, 'returnsNull');
    $paths = DependencyRecorder::paths();
    DependencyRecorder::stop();

    expect($paths)->toContain((string) new ReflectionClass(LiteralNullReturns::class)->getFileName());
});

// self:: and a private method run the class whose body is read; static:: runs the class the method is read on.
it('reads a stop a child redeclares as never by the class the call resolves to', function (string $method, bool $literal) {
    $run = fn (): mixed => (new StoppingChild)->{$method}(1);

    expect(resolve(ReturnLiteralReader::class)->returnsNull(StoppingChild::class, $method))->toBe($literal);
    $literal ? expect($run())->toBeNull() : expect($run)->toThrow(LogicException::class);
})->with([
    'self::stop(), the parent\'s, which returns' => ['endsInSelfStop', true],
    'static::stop(), the child\'s, which never returns' => ['endsInStaticStop', false],
    'a private halt(), the parent\'s, which returns' => ['endsInPrivateHalt', true],
]);
