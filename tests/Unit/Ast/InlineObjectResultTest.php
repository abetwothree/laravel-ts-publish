<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Values\InlineObjectResult;
use Workbench\App\Models\User;

it('reads every member a fill typed class-less, keeping a value written in place', function () {
    $analysis = new MethodAnalysis;
    $analysis->addProperty('override', ['type' => 'User', 'optional' => false, 'customImports' => ['@js/types/ui' => ['User']]]);
    $analysis->addProperty('in_place', ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class]);
    $analysis->addProperty('filled', ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class]);
    $analysis->declaredTokenFqcns['filled'] = [User::class];

    InlineObjectResult::readFillsClassLess($analysis);

    expect(array_column($analysis->properties, 'type', 'name'))->toBe(['override' => 'User', 'in_place' => 'User', 'filled' => 'unknown'])
        ->and($analysis->tokenFqcns('in_place'))->toBe([User::class])
        ->and($analysis->hasFqcnChannel('filled'))->toBeFalse()
        ->and($analysis->declaredTokenFqcns)->toBe([])
        ->and($analysis->customImports)->toBe(['@js/types/ui' => ['User']]);
});

it('reads a fill class-less whatever its siblings', function () {
    $analysis = new MethodAnalysis;
    $analysis->addProperty('filled', ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class]);
    $analysis->declaredTokenFqcns['filled'] = [User::class];

    InlineObjectResult::readFillsClassLess($analysis);

    expect(array_column($analysis->properties, 'type', 'name'))->toBe(['filled' => 'unknown'])
        ->and($analysis->hasFqcnChannel('filled'))->toBeFalse();
});
