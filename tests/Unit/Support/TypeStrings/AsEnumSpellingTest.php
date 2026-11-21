<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\AsEnumSpelling;

it('spells a wrap of a const, and a list of them', function () {
    expect(AsEnumSpelling::of('Status'))->toBe('AsEnum<typeof Status>')
        ->and(AsEnumSpelling::of('app.enums.Status', list: true))->toBe('AsEnum<typeof app.enums.Status>[]');
});

it('reads back what it spells', function (string $const, bool $list) {
    expect(preg_match('/'.AsEnumSpelling::pattern($const).'/', AsEnumSpelling::of($const, $list), $match))->toBe(1)
        ->and($match['const'])->toBe($const);
})->with([
    'a const' => ['Status', false],
    'a list' => ['Status', true],
    'an aliased const' => ['CrmStatus', false],
    'a qualified globals name' => ['crm.enums.Status', false],
]);

it('reads only the one const it is given', function (string $type, bool $expected) {
    expect(preg_match('/'.AsEnumSpelling::pattern('Status').'/', $type))->toBe($expected ? 1 : 0);
})->with([
    'that const' => ['AsEnum<typeof Status>', true],
    'that const, spaced' => ['AsEnum< typeof  Status >', true],
    'a longer const' => ['AsEnum<typeof StatusKind>', false],
    'a qualified const' => ['AsEnum<typeof app.Status>', false],
    'the bare type' => ['Status', false],
]);

it('quotes a const for the pattern', function () {
    expect(preg_match('/'.AsEnumSpelling::pattern('app.Status').'/', 'AsEnum<typeof appXStatus>'))->toBe(0)
        ->and(preg_match('/'.AsEnumSpelling::pattern('app.Status').'/', 'AsEnum<typeof app.Status>'))->toBe(1);
});
