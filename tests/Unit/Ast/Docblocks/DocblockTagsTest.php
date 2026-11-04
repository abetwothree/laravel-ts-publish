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
    ["/**\n * @return list<Post>\n * @phpstan-return list\n */", 'list<Post>'],
    ["/**\n * @return Post[]\n * @phpstan-return mixed[]\n */", 'Post[]'],
]);

test('a tag is read whole and only at the start of a line', function () {
    expect(DocblockTags::type("/**\n * @return static | null\n */", 'return'))->toBe('static | null')
        ->and(DocblockTags::type("/**\n * Mentions @return here.\n * @return int\n */", 'return'))->toBe('int')
        ->and(DocblockTags::type('/** @phpstan-var list<int> */', 'var'))->toBe('list<int>');
});

test('a quoted literal is read whole, a backslash escaping the byte after it', function () {
    expect(DocblockTags::type("/** @return 'foo bar'|null */", 'return'))->toBe("'foo bar'|null")
        ->and(DocblockTags::type('/** @return "a b" */', 'return'))->toBe('"a b"')
        ->and(DocblockTags::type("/** @return 'it\\'s here'|null The note */", 'return'))->toBe("'it\\'s here'|null")
        ->and(DocblockTags::named("/** @property 'in review'|'draft' \$status */", 'property'))
        ->toBe(['status' => "'in review'|'draft'"]);
});

test('a quote no closer ends on its line stays an ordinary character', function () {
    expect(DocblockTags::named("/**\n * @param 'open \$x\n * @param int \$y The 'quoted' y\n */", 'param'))
        ->toBe(['x' => "'open", 'y' => 'int']);
});

test('a quoted closer or space inside a multi-line shape neither ends the shape nor the type', function () {
    expect(DocblockTags::type("/**\n * @return array{\n *   'a} b': int,\n *   c: string\n * } The pair\n */", 'return'))
        ->toBe("array{    'a} b': int,    c: string  }");
});

test('a space before a generic\'s or a shape\'s opener continues the type, but not one before a parenthesis', function () {
    expect(DocblockTags::named('/** @property array <string, int> $counts */', 'property'))
        ->toBe(['counts' => 'array <string, int>'])
        ->and(DocblockTags::all('/** @use HasItems <Post> */', 'use'))->toBe(['HasItems <Post>'])
        ->and(DocblockTags::type("/**\n * @return int (the count)\n */", 'return'))->toBe('int');
});

test('ends a type before an inline tag, though a space before a shape\'s brace continues it', function () {
    expect(DocblockTags::type('/** @return string {@inheritdoc} */', 'return'))->toBe('string')
        ->and(DocblockTags::type('/** @return array{a: int} {@see Foo} */', 'return'))->toBe('array{a: int}')
        ->and(DocblockTags::type('/** @return array {a: int} */', 'return'))->toBe('array {a: int}');
});

test('captures a callable tag whole, up to the by-reference name after its return type', function () {
    expect(DocblockTags::type('/** @var callable(int): string &$cb */', 'var'))->toBe('callable(int): string');
});

test('reads a tag\'s type and its name only on the tag\'s line, though a type it opens there may run on', function () {
    expect(DocblockTags::type("/**\n * @return\n * The user\n */", 'return'))->toBeNull()
        ->and(DocblockTags::type("/**\n * @return \n * The user\n */", 'return'))->toBeNull()
        ->and(DocblockTags::named("/**\n * @param int\n * \$x is the count\n */", 'param'))->toBe([])
        ->and(DocblockTags::named("/**\n * @param int &\n * ...\$x\n */", 'param'))->toBe([])
        ->and(DocblockTags::named("/**\n * @param array{\n *   a: int\n * } \$x\n */", 'param'))->toBe(['x' => 'array{    a: int  }']);
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
    ['list', true], ['object', true], ['non-empty-array', true], ['non-empty-list', true], ['mixed[]', true],
    ['?list|null', true], ['non-empty-array<string, mixed>', true], ['iterable<mixed>', true],
    ['EloquentCollection<int, mixed>', true], ['Enumerable<int, mixed>', true],
    ['\\Illuminate\\Support\\Collection<int, mixed>', true],
    ['array{a: int}', false], ['list<string>', false], ['Collection<int, Post>', false], ['string', false],
    ['Post[]', false], ['array<string, list<mixed>>', false], ['list<Post>|mixed', false], ['null', false],
]);
