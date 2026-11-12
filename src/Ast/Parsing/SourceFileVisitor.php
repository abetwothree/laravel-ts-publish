<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Parsing;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Writes the file a node was parsed from onto it, so a node keeps one identity, its file and position, across the
 * parses of a file AstParser's cache evicts and reads again.
 *
 * @internal
 */
final class SourceFileVisitor extends NodeVisitorAbstract
{
    /** The node attribute holding the parsed file's path. */
    public const string ATTRIBUTE = 'sourceFile';

    /**
     * Visit the nodes of the file at $path.
     */
    public function __construct(private readonly string $path) {}

    /**
     * Write the file's path onto the node.
     */
    public function enterNode(Node $node): null
    {
        $node->setAttribute(self::ATTRIBUTE, $this->path);

        return null;
    }
}
