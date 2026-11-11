<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Values\InlineObjectResult;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\App\Models\User;
use Workbench\Crm\Enums\Status as CrmStatus;

it('reads every member a fill typed class-less, keeping a value written in place', function () {
    $analysis = new MethodAnalysis;
    $analysis->addProperty('override', ['type' => 'User', 'optional' => false, 'customImports' => ['@js/types/ui' => ['User']]]);
    $analysis->addProperty('in_place', ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class]);
    $analysis->addProperty('filled', ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class]);
    $analysis->declaredTokenFqcns['filled'] = [User::class];

    InlineObjectResult::readFillsClassLess($analysis);

    expect(array_column($analysis->properties, 'type', 'name'))->toBe(['override' => 'User', 'in_place' => 'User', 'filled' => 'unknown'])
        ->and($analysis->channelFqcns('in_place'))->toBe([User::class])
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

it('queues the enums a member wraps on the object\'s enum queue, as their tokens read bare', function (bool $tolki) {
    config()->set('ts-publish.enums.use_tolki_package', $tolki);
    $analysis = new MethodAnalysis;
    $analysis->addProperty('first', ['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => CrmStatus::class]);
    $analysis->addProperty('subject', ['type' => 'StatusType | RoleType', 'optional' => false, 'multiEnumResourceFqcns' => [Status::class, Role::class]]);

    $result = new InlineObjectResult()->of($analysis);

    expect($result['type'])->toBe('{ first: StatusType; subject: StatusType | RoleType }')
        ->and($result['embeddedEnumFqcns'] ?? [])->toBe([CrmStatus::class, Status::class, Role::class])
        ->and($result)->not->toHaveKey('embeddedEnumResourceFqcns');
})->with(['tolki on' => [true], 'tolki off' => [false]]);
