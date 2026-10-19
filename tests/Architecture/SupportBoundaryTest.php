<?php

declare(strict_types=1);

// docs/components/support-helpers.md: the engine calls the Support helpers, and they never call back.
arch('the Support helpers depend on none of the code that calls them')
    ->expect('AbeTwoThree\LaravelTsPublish\Support')
    ->not->toUse([
        'AbeTwoThree\LaravelTsPublish\Ast',
        'AbeTwoThree\LaravelTsPublish\Analyzers',
        'AbeTwoThree\LaravelTsPublish\LaravelTsPublish',
        'AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish',
        'AbeTwoThree\LaravelTsPublish\ModelAttributeResolver',
        'AbeTwoThree\LaravelTsPublish\Transformers',
        'AbeTwoThree\LaravelTsPublish\Concerns',
    ]);
