<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/**
 * Single php-parser entry point: name-resolved ASTs, cached per file, recorded as cache dependencies.
 *
 * @internal
 */
class AstParser
{
    /** Spread analysis re-reads the same file many times per run; the cap keeps a large app from ballooning memory. */
    private const int MAX_CACHED_FILES = 128;

    /** @var array<string, array<Node>> */
    protected array $fileAsts = [];

    /**
     * Parse a file into a name-resolved AST, caching per path and recording the cache dependency.
     *
     * @return array<Node>
     */
    public function parseFile(string $path): array
    {
        DependencyRecorder::record($path);

        if (isset($this->fileAsts[$path])) {
            return $this->fileAsts[$path];
        }

        if (count($this->fileAsts) >= self::MAX_CACHED_FILES) {
            array_shift($this->fileAsts);
        }

        return $this->fileAsts[$path] = $this->parseSource((string) file_get_contents($path));
    }

    /**
     * Parse raw PHP source into a name-resolved AST. Uncached; prefer parseFile() for on-disk code.
     *
     * @return array<Node>
     */
    public function parseSource(string $source): array
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();

        try {
            $stmts = $parser->parse($source);
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
