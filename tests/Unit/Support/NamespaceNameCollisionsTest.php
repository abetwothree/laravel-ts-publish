<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\NamespaceNameCollisions;

test('a later class that publishes a name an earlier class publishes in its namespace collides with it', function () {
    expect(NamespaceNameCollisions::find([
        ['namespace' => 'app/enums', 'fqcn' => 'App\Enums\Role', 'names' => ['Role', 'RoleType', 'RoleKind']],
        ['namespace' => 'app/enums', 'fqcn' => 'App\Enums\RoleType', 'names' => ['RoleType', 'RoleTypeType', 'RoleTypeKind']],
        ['namespace' => 'crm/enums', 'fqcn' => 'Crm\Enums\RoleType', 'names' => ['RoleType', 'RoleTypeType']],
    ]))->toBe([['subject' => 'App\Enums\RoleType', 'name' => 'RoleType', 'other' => 'App\Enums\Role']]);
});

test('a class listed twice, or a name one class lists twice, collides with nothing', function () {
    expect(NamespaceNameCollisions::find([
        ['namespace' => 'app/enums', 'fqcn' => 'App\Enums\Role', 'names' => ['Role', 'Role']],
        ['namespace' => 'app/enums', 'fqcn' => 'App\Enums\Role', 'names' => ['Role']],
    ]))->toBe([]);
});
