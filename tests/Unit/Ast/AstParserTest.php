<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;

it('parses source with resolved names', function () {
    $stmts = new AstParser()->parseSource('<?php namespace A; use B\C; class D extends C {}');

    expect($stmts)->toHaveCount(1)
        ->and($stmts[0])->toBeInstanceOf(Namespace_::class);

    $class = $stmts[0]->stmts[1];
    expect($class)->toBeInstanceOf(Class_::class)
        ->and($class->extends?->toString())->toBe('B\C');
});

it('parses a file, caches it, and records it as a dependency even on a cache hit', function () {
    $parser = new AstParser;
    $file = (new ReflectionClass(AstParser::class))->getFileName();

    DependencyRecorder::start();
    $first = $parser->parseFile($file);
    DependencyRecorder::reset();
    DependencyRecorder::start();
    $second = $parser->parseFile($file);
    $paths = DependencyRecorder::paths();
    DependencyRecorder::stop();
    DependencyRecorder::reset();

    expect($second)->toBe($first)
        ->and($paths)->toContain($file);
});

it('returns an empty array for unparseable source', function () {
    expect(new AstParser()->parseSource(''))->toBe([]);
});

it('returns an empty array for syntactically invalid source', function () {
    expect(new AstParser()->parseSource('<?php class {'))->toBe([]);
});
