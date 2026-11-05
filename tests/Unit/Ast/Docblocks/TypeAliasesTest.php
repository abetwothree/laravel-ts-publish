<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\TypeAliases;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\LocalAliasResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\TypeAliasShapeModel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ImportedAliasProperties;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\LocalAliasSource;
use Workbench\App\Models\Team;
use Workbench\App\ValueObjects\GridConfigDto;

test('finds an alias a class defines or imports, with the class whose docblock defines it', function (string $name, string $context, ?array $expected) {
    expect(TypeAliases::find($name, new ReflectionClass($context)))->toBe($expected);
})->with([
    'a definition' => ['Pair', LocalAliasResource::class, ['definition' => 'array{a: int, b: string}', 'class' => LocalAliasResource::class]],
    'a definition written with =' => ['GridPreset', GridConfigDto::class, ['definition' => 'array{name: string, locked?: bool}', 'class' => GridConfigDto::class]],
    'an import' => ['Flag', LocalAliasResource::class, ['definition' => 'array{c: bool, d: list<int>}', 'class' => LocalAliasSource::class]],
    'a renamed import' => ['Duo', LocalAliasResource::class, ['definition' => 'array{c: bool, d: list<int>}', 'class' => LocalAliasSource::class]],
    'an import a model reads' => ['GridPreset', Team::class, ['definition' => 'array{name: string, locked?: bool}', 'class' => GridConfigDto::class]],
    'an alias naming itself, as written' => ['Tree', TypeAliasShapeModel::class, ['definition' => 'list<Tree>', 'class' => TypeAliasShapeModel::class]],
    'a name no tag binds' => ['Missing', LocalAliasResource::class, null],
    'a class the docblock names, not an alias' => ['LocalAliasSource', LocalAliasResource::class, null],
]);

test('answers null where a lookup along the import chain is already under way', function () {
    $context = new ReflectionClass(LocalAliasResource::class);

    expect(TypeAliases::lookup('Duo', $context, [LocalAliasSource::class.'::Flag' => true]))->toBeNull()
        ->and(TypeAliases::lookup('Duo', $context, [LocalAliasResource::class.'::Duo' => true]))->toBeNull()
        ->and(TypeAliases::lookup('Pair', $context, [LocalAliasResource::class.'::Duo' => true])['definition'] ?? null)
        ->toBe('array{a: int, b: string}');
});

test('reads a name as an alias only when it is a PHPDoc identifier', function (string $name, bool $expected) {
    expect(TypeAliases::isName($name))->toBe($expected);
})->with([
    'a plain name' => ['Pair', true],
    'a hyphen after the first character' => ['pair-shape', true],
    'non-ASCII letters' => ['Größe', true],
    'a leading digit' => ['1Pair', false],
    'a leading hyphen' => ['-pair', false],
    'a generic' => ['int<0, max>', false],
    'a qualified name' => ['App\\Pair', false],
]);

test('walks a multi-line definition to the bracket that closes it', function () {
    $doc = "/**\n * @phpstan-type Row array{\n *     a: int,\n *     b: string,\n * }\n * @phpstan-type Next int\n */";

    expect(TypeAliases::balance('array{', $doc, (int) strpos($doc, "\n", (int) strpos($doc, 'array{'))))
        ->toBe('array{ a: int, b: string, }')
        ->and(TypeAliases::balance('int', $doc, 0))->toBe('int');
});

test('keeps each answer until it is flushed', function () {
    TypeAliases::find('Row', new ReflectionClass(ImportedAliasProperties::class));

    expect(TypeAliases::answers()[ImportedAliasProperties::class] ?? null)
        ->toBe(['Row' => ['definition' => 'array{a: int}', 'class' => TypeAliasShapeModel::class]])
        ->and(TypeAliases::answerPaths())->toHaveKey(ImportedAliasProperties::class);

    TypeAliases::flush();

    expect(TypeAliases::answers())->toBe([])
        ->and(TypeAliases::answerPaths())->toBe([]);
});
