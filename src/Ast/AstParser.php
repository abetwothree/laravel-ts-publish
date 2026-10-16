<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Single php-parser entry point: name-resolved ASTs, cached per file, recorded as cache dependencies.
 *
 * @phpstan-type MethodsByName = array<string, list<ClassMethod>>
 *
 * @internal
 */
class AstParser
{
    /** Spread analysis re-reads the same file many times per run; the cap keeps a large app from ballooning memory. */
    private const int MAX_CACHED_FILES = 128;

    /** @var array<string, array<Node>> */
    protected array $fileAsts = [];

    /** @var array<string, MethodsByName> each cached file's methods by lowercased name, evicted with its AST */
    protected array $fileMethods = [];

    /** Built once and reused: a run parses hundreds of files, and a fresh parser for each one adds up. */
    protected Parser $parser;

    /**
     * Build the one parser every parse reuses.
     */
    public function __construct()
    {
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
    }

    /**
     * Parse a file into a name-resolved AST, caching per path and recording the cache dependency.
     *
     * @return array<Node>
     */
    public function parseFile(string $path): array
    {
        DependencyRecorder::record($path);

        if (isset($this->fileAsts[$path])) {
            $stmts = $this->fileAsts[$path];

            // Re-inserted last so eviction drops the least recently read file: a model or framework file read across
            // many classes would otherwise be re-parsed whenever enough newer files pushed it out.
            unset($this->fileAsts[$path]);

            return $this->fileAsts[$path] = $stmts;
        }

        if (count($this->fileAsts) >= self::MAX_CACHED_FILES) {
            $evicted = array_key_first($this->fileAsts);
            unset($this->fileAsts[$evicted], $this->fileMethods[$evicted]);
        }

        return $this->fileAsts[$path] = $this->parseSource((string) file_get_contents($path));
    }

    /**
     * A file's AST, as parseFile() returns it, with the methods it declares by lowercased name, in the order a walk
     * meets them. A file is walked for its methods once while it stays cached, however many of them are looked up.
     *
     * @return array{array<Node>, MethodsByName}
     */
    public function parseFileMethods(string $path): array
    {
        $stmts = $this->parseFile($path);

        if (! isset($this->fileMethods[$path])) {
            $methods = [];

            foreach (new NodeFinder()->findInstanceOf($stmts, ClassMethod::class) as $method) {
                $methods[$method->name->toLowerString()][] = $method;
            }

            $this->fileMethods[$path] = $methods;
        }

        return [$stmts, $this->fileMethods[$path]];
    }

    /**
     * Parse raw PHP source into a name-resolved AST. Uncached; prefer parseFile() for on-disk code.
     *
     * @return array<Node>
     */
    public function parseSource(string $source): array
    {
        try {
            $stmts = $this->parser->parse($source);
        } catch (Error) {
            return [];
        }

        if ($stmts === null) {
            return []; // @codeCoverageIgnore
        }

        $traverser = new NodeTraverser;
        $traverser->addVisitor(new NameResolver);

        return $traverser->traverse($stmts);
    }
}
