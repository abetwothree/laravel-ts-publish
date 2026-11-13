<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Calls\KnownMethodRuleHandler;

arch('the AST engine depends on no analyzer')
    ->expect('AbeTwoThree\LaravelTsPublish\Ast')
    ->not->toUse('AbeTwoThree\LaravelTsPublish\Analyzers')
    // These files imported an analyzer before AGENTS.md § AST & Analyzers; the list may only shrink, never grow.
    ->ignoring([
        AstEngine::class,
        KnownMethodRuleHandler::class,
    ]);

// Handlers read the scope; a scope reading a handler would reach the analyzer traits that handler uses.
arch('the analysis scope depends on no handler')
    ->expect(AnalysisScope::class)
    ->not->toUse('AbeTwoThree\LaravelTsPublish\Ast\Handlers');
