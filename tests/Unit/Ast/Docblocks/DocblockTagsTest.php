<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\DocblockTags;

test('a prefixed tag wins over the plain one, in PHPStan\'s order', function (string $doc, string $expected) {
    expect(DocblockTags::type($doc, 'return'))->toBe($expected);
})->with([
    ["/**\n * @return string\n * @phpstan-return int\n */", 'int'],
    ["/**\n * @psalm-return int\n * @return string\n */", 'int'],
    ["/**\n * @psalm-return int\n * @phpstan-return float\n */", 'float'],
]);

test('a vague prefixed tag yields to a precise plain one', function (string $doc, string $expected) {
    expect(DocblockTags::type($doc, 'return'))->toBe($expected);
})->with([
    ["/**\n * @return array{a: int}\n * @phpstan-return array<string, mixed>\n */", 'array{a: int}'],
    ["/**\n * @return array<string, mixed>\n * @phpstan-return array{a: int, b: string}\n */", 'array{a: int, b: string}'],
    ["/**\n * @return mixed\n * @phpstan-return HasMany<Comment, \$this>\n */", 'HasMany<Comment, $this>'],
]);

test('a tag is read whole and only at the start of a line', function () {
    expect(DocblockTags::type("/**\n * @return static | null\n */", 'return'))->toBe('static | null')
        ->and(DocblockTags::type("/**\n * Mentions @return here.\n * @return int\n */", 'return'))->toBe('int')
        ->and(DocblockTags::type('/** @phpstan-var list<int> */', 'var'))->toBe('list<int>');
});

test('a family with no tag, or only prose mentions of one, reads null', function () {
    expect(DocblockTags::type("/**\n * Mentions @return int here.\n */", 'return'))->toBeNull()
        ->and(DocblockTags::type('/** @returns int */', 'return'))->toBeNull()
        ->and(DocblockTags::named('/** @property-write int $id */', 'property'))->toBe([])
        ->and(DocblockTags::all('/** @uses Helper */', 'use'))->toBe([]);
});

test('named tags merge by name, the prefixed one winning', function () {
    expect(DocblockTags::named("/**\n * @param string \$a\n * @phpstan-param non-empty-string \$a\n * @param int \$b\n */", 'param'))
        ->toBe(['a' => 'non-empty-string', 'b' => 'int'])
        ->and(DocblockTags::named("/**\n * @property-read int \$id\n * @phpstan-property list<string> \$tags\n */", 'property'))
        ->toBe(['tags' => 'list<string>', 'id' => 'int']);
});

test('a vague prefixed named tag yields to a precise plain one for its name', function () {
    expect(DocblockTags::named("/**\n * @phpstan-param array<string, mixed> \$a\n * @param array{x: int} \$a\n */", 'param'))
        ->toBe(['a' => 'array{x: int}']);
});

test('a parameter name is read past a by-reference or variadic marker', function () {
    expect(DocblockTags::named("/**\n * @param int &\$a\n * @param string ...\$b\n * @param bool &...\$c\n */", 'param'))
        ->toBe(['a' => 'int', 'b' => 'string', 'c' => 'bool']);
});

test('a $-less @property names its property only when no description follows', function () {
    expect(DocblockTags::named("/**\n * @property string[] labels\n * @property string[] tags Friendly labels\n */", 'property'))
        ->toBe(['labels' => 'string[]'])
        ->and(DocblockTags::named('/** @param int count */', 'param'))->toBe([]);
});

test('all() lists every tag of a family, prefixed tags first', function () {
    expect(DocblockTags::all("/**\n * @use Holds<Post>\n * @template-use Lists<int>\n * @psalm-use Bags<Tag>\n * @phpstan-use Keeps<User>\n */", 'use'))
        ->toBe(['Keeps<User>', 'Bags<Tag>', 'Lists<int>', 'Holds<Post>']);
});

test('each template name is listed once', function () {
    expect(DocblockTags::templates("/**\n * @template T\n * @phpstan-template T\n * @template-covariant U\n */"))->toBe(['T', 'U']);
});

test('isVague() reads the PHPDoc forms that carry no element type', function (string $type, bool $vague) {
    expect(DocblockTags::isVague($type))->toBe($vague);
})->with([
    ['mixed', true], ['array', true], ['?array', true], ['array|null', true], ['iterable', true],
    ['array<string, mixed>', true], ['list<mixed>', true], ['Collection<int, mixed>', true],
    ['array{a: int}', false], ['list<string>', false], ['Collection<int, Post>', false], ['string', false],
]);
