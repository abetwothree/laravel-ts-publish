<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\ReturnLiteralReader;
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

it('returns null for a method with more than one return', function () {
    expect(resolve(ReturnLiteralReader::class)->stringLiteral(SubjectProps::class, 'twoLiteralReturns'))
        ->toBeNull();
});
