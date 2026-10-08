<?php

declare(strict_types=1);

// docs/components/support-helpers.md: the engine calls the Support helpers, and they never call back.
arch('the Support helpers depend on neither the AST engine nor the type engine')
    ->expect('AbeTwoThree\LaravelTsPublish\Support')
    ->not->toUse([
        'AbeTwoThree\LaravelTsPublish\Ast',
        'AbeTwoThree\LaravelTsPublish\Analyzers',
        'AbeTwoThree\LaravelTsPublish\LaravelTsPublish',
        'AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish',
    ]);
