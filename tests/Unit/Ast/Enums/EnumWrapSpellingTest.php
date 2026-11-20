<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Enums\EnumWrapSpelling;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Workbench\App\Enums\Status;

it('spells a wrap of a const, and a list of them', function () {
    expect(EnumWrapSpelling::of('Status'))->toBe('AsEnum<typeof Status>')
        ->and(EnumWrapSpelling::of('WorkbenchStatus', list: true))->toBe('AsEnum<typeof WorkbenchStatus>[]');
});

it('spells a wrap of an enum by its const name', function () {
    expect(EnumWrapSpelling::ofEnum(Status::class))->toBe('AsEnum<typeof Status>')
        ->and(EnumWrapSpelling::ofEnum(Status::class, list: true))->toBe('AsEnum<typeof Status>[]');
});

it('follows the tolki package flag', function (bool $flag) {
    config()->set('ts-publish.enums.use_tolki_package', $flag);

    expect(EnumWrapSpelling::enabled())->toBe($flag);
})->with([true, false]);

it('reads a wrap of any const, a qualified globals name included', function (string $type, ?string $const) {
    $matched = preg_match('/'.EnumWrapSpelling::PATTERN.'/', $type, $match) === 1;

    expect($matched ? $match['const'] : null)->toBe($const);
})->with([
    'a bare const' => ['AsEnum<typeof Status>', 'Status'],
    'a spaced, qualified const' => ['AsEnum< typeof app.enums.Status >', 'app.enums.Status'],
    'no typeof' => ['AsEnum<Status>', null],
]);

it('is the only place src spells AsEnum<typeof', function () {
    $root = dirname(__DIR__, 4);
    $owner = 'src/Support/TypeStrings/AsEnumSpelling.php';
    $spellers = [];

    foreach (new Finder()->files()->in($root.'/src')->name('*.php') as $file) {
        /** @var SplFileInfo $file */
        foreach (PhpToken::tokenize($file->getContents()) as $token) {
            if ($token->is([T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE]) && str_contains($token->text, 'AsEnum<typeof')) {
                $spellers[] = 'src/'.$file->getRelativePathname().':'.$token->line;
            }
        }
    }

    foreach (new Finder()->files()->in($root.'/resources/views') as $file) {
        /** @var SplFileInfo $file */
        if (str_contains($file->getContents(), 'AsEnum<typeof')) {
            $spellers[] = 'resources/views/'.$file->getRelativePathname();
        }
    }

    expect(array_values(array_filter($spellers, fn (string $at): bool => ! str_starts_with($at, $owner.':'))))->toBe([])
        ->and($spellers)->not->toBe([]);
});
