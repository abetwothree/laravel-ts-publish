<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Parsing;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\NodeVisitorAbstract;

/**
 * Hands a call argument's comments to the closure it holds, so a docblock written before a closure argument types it,
 * and writes the file a node was parsed from onto it (AstParser::SOURCE_FILE).
 *
 * php-parser gives a comment to the first node that starts after it. One after an array key's `=>` or a named
 * argument's `:` lands on the closure; one before a positional argument, which starts on its closure's token, or before
 * a named argument's name lands on the `Arg`, which this visitor moves.
 *
 * @internal
 */
final class ArgumentDocCommentVisitor extends NodeVisitorAbstract
{
    /**
     * Visit the nodes of the file at $path, or of a source no file holds.
     */
    public function __construct(private readonly ?string $path = null) {}

    /**
     * Move an argument's comments onto the closure it holds, unless the closure has a docblock of its own, and mark
     * the node with its file.
     */
    public function enterNode(Node $node): null
    {
        if ($node instanceof Arg
            && ($node->value instanceof Closure || $node->value instanceof ArrowFunction)
            && $node->value->getDocComment() === null
            && $node->getDocComment() !== null
        ) {
            $node->value->setAttribute('comments', [...$node->getComments(), ...$node->value->getComments()]);
            $node->setAttribute('comments', []);
        }

        if ($this->path !== null) {
            $node->setAttribute(AstParser::SOURCE_FILE, $this->path);
        }

        return null;
    }
}
