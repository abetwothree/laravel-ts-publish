<?php

declare(strict_types=1);

// The model, its operations and its printer read shapes alone, so each stays testable without a parse tree; a `Node`
// site is allowed, an expression is not.
arch('the array model, its operations and its printer use no expression node')
    ->expect([
        'AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape',
        'AbeTwoThree\LaravelTsPublish\Ast\Arrays\Ops',
        'AbeTwoThree\LaravelTsPublish\Ast\Arrays\Print',
    ])
    ->not->toUse('PhpParser\Node\Expr');

arch('the array printer reads no builder and no reader')
    ->expect('AbeTwoThree\LaravelTsPublish\Ast\Arrays\Print')
    ->not->toUse(['AbeTwoThree\LaravelTsPublish\Ast\Arrays\Build', 'AbeTwoThree\LaravelTsPublish\Ast\Arrays\Read']);

// A check the tests bind is bound from tests/Pest.php, never switched on by an environment variable in shipped code.
arch('the engine reads no environment variable')
    ->expect('AbeTwoThree\LaravelTsPublish\Ast')
    ->not->toUse('getenv');
