<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ConditionalMethodHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\InertiaResourcePropHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\KnownMethodRuleHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\NewResourceHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\StaticCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ThisPropertyHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ToResourceHandler;

arch('the AST engine depends on no analyzer')
    ->expect('AbeTwoThree\LaravelTsPublish\Ast')
    ->not->toUse('AbeTwoThree\LaravelTsPublish\Analyzers')
    // These files imported an analyzer before AGENTS.md § AST & Analyzers; the list may only shrink, never grow.
    ->ignoring([
        AstEngine::class,
        ConditionalMethodHandler::class,
        InertiaResourcePropHandler::class,
        KnownMethodRuleHandler::class,
        NewResourceHandler::class,
        StaticCallHandler::class,
        ThisPropertyHandler::class,
        ToResourceHandler::class,
    ]);
