<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeFinder;

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

it('parses valid source again after a syntax error on the same instance', function () {
    $parser = new AstParser;

    expect($parser->parseSource('<?php class {'))->toBe([])
        ->and($parser->parseSource('<?php namespace A; class B {}')[0])->toBeInstanceOf(Namespace_::class);
});

it('evicts the least recently read file once the cache is full', function () {
    $cap = new ReflectionClassConstant(AstParser::class, 'MAX_CACHED_FILES')->getValue();
    $dir = sys_get_temp_dir().'/ast-parser-lru-'.uniqid();
    mkdir($dir);
    $paths = array_map(function (int $i) use ($dir): string {
        file_put_contents($path = "{$dir}/file{$i}.php", "<?php \$value = {$i};");

        return $path;
    }, range(0, $cap));

    try {
        $parser = new AstParser;
        $first = $parser->parseFile($paths[0]);
        $second = $parser->parseFile($paths[1]);

        foreach (array_slice($paths, 2, $cap - 2) as $path) {
            $parser->parseFile($path);
        }

        $parser->parseFile($paths[0]);
        $parser->parseFile($paths[$cap]);

        expect($parser->parseFile($paths[0]))->toBe($first)
            ->and($parser->parseFile($paths[1]))->not->toBe($second);
    } finally {
        array_map(unlink(...), $paths);
        rmdir($dir);
    }
});

// Each method node must belong to the AST handed back beside it, since a caller walks that AST around the node.
it('walks a cached file for its methods once, and again from the AST that replaces an evicted one', function () {
    $cap = new ReflectionClassConstant(AstParser::class, 'MAX_CACHED_FILES')->getValue();
    $dir = sys_get_temp_dir().'/ast-parser-methods-'.uniqid();
    mkdir($dir);
    file_put_contents($subject = "{$dir}/Subject.php", '<?php class Subject { function read() {} function Read() {} '
        .'function other() { return new class { function read() {} }; } }');
    $fillers = array_map(function (int $i) use ($dir): string {
        file_put_contents($path = "{$dir}/file{$i}.php", "<?php \$value = {$i};");

        return $path;
    }, range(1, $cap));
    $owns = fn (array $stmts, ClassMethod $method): bool => new NodeFinder()
        ->findFirst($stmts, fn (Node $node): bool => $node === $method) !== null;
    $names = fn (array $methods): array => array_map(
        fn (ClassMethod $method): string => $method->name->toString(),
        $methods,
    );

    try {
        $parser = new AstParser;
        [$stmts, $methods] = $parser->parseFileMethods($subject);
        [$cached, $again] = $parser->parseFileMethods($subject);
        $seeded = new class extends AstParser
        {
            /** Plants an index entry so a lookup that rebuilds instead of reusing shows. */
            public function plant(string $path, array $stmts): void
            {
                $this->fileMethods[$path] = [$stmts, ['planted' => []]];
            }
        };
        $seeded->plant($subject, $seeded->parseFile($subject));

        foreach ($fillers as $path) {
            $parser->parseFile($path);
        }

        // An index kept from an evicted AST must not be served beside the AST that replaced it.
        $stale = clone $seeded;
        $stale->plant($subject, [new Node\Stmt\Nop]);

        [$reparsed, $fresh] = $parser->parseFileMethods($subject);

        expect($names($methods['read']))->toBe(['read', 'Read', 'read'])
            ->and(array_keys($methods))->toBe(['read', 'other'])
            ->and($cached)->toBe($stmts)
            ->and(array_keys($seeded->parseFileMethods($subject)[1]))->toBe(['planted'])
            ->and($again['read'][0])->toBe($methods['read'][0])
            ->and($reparsed)->not->toBe($stmts)
            ->and($owns($reparsed, $fresh['read'][0]))->toBeTrue()
            ->and($owns($reparsed, $methods['read'][0]))->toBeFalse()
            ->and(array_keys($stale->parseFileMethods($subject)[1]))->toBe(['read', 'other']);
    } finally {
        array_map(unlink(...), [$subject, ...$fillers]);
        rmdir($dir);
    }
});
