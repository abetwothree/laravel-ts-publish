<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Values\InlineObjectResult;
use Workbench\App\Models\User;

it('reads class-less only a fill whose class a sibling\'s import names, keeping a value written in place', function () {
    $analysis = new MethodAnalysis;
    $analysis->addProperty('override', ['type' => 'User', 'optional' => false, 'customImports' => ['@js/types/ui' => ['User']]]);
    $analysis->addProperty('in_place', ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class]);
    $analysis->addProperty('filled', ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class]);
    $analysis->declaredTokenFqcns['filled'] = [User::class];

    InlineObjectResult::readUnbindableFillsClassLess($analysis);

    expect(array_column($analysis->properties, 'type', 'name'))->toBe(['override' => 'User', 'in_place' => 'User', 'filled' => 'unknown'])
        ->and($analysis->tokenFqcns('in_place'))->toBe([User::class])
        ->and($analysis->hasFqcnChannel('filled'))->toBeFalse()
        ->and($analysis->declaredTokenFqcns)->toBe([])
        ->and($analysis->customImports)->toBe(['@js/types/ui' => ['User']]);
});

it('keeps a fill whose class an import names where no sibling spells that name', function () {
    $analysis = new MethodAnalysis(customImports: ['@js/types/ui' => ['User']]);
    $analysis->addProperty('gone', ['type' => 'unknown', 'optional' => false]);
    $analysis->addProperty('filled', ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class]);
    $analysis->declaredTokenFqcns['filled'] = [User::class];

    InlineObjectResult::readUnbindableFillsClassLess($analysis);

    expect(array_column($analysis->properties, 'type', 'name'))->toBe(['gone' => 'unknown', 'filled' => 'User'])
        ->and($analysis->tokenFqcns('filled'))->toBe([User::class]);
});

it('keeps a fill whose slots hold no null and whose class no import names', function () {
    $analysis = new MethodAnalysis;
    $analysis->addProperty('filled', ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class]);
    $analysis->declaredTokenFqcns['filled'] = [User::class];

    InlineObjectResult::readUnbindableFillsClassLess($analysis);

    expect(array_column($analysis->properties, 'type', 'name'))->toBe(['filled' => 'User'])
        ->and($analysis->tokenFqcns('filled'))->toBe([User::class])
        ->and($analysis->declaredTokenFqcns)->toBe(['filled' => [User::class]]);
});
