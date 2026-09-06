<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

/**
 * Reads a method whose whole body is one string-literal return, e.g. `broadcastAs()`.
 *
 * Whole literal or nothing: a concatenation folded to its literal prefix ships a wrong Echo key.
 *
 * @internal
 */
final class ReturnLiteralReader
{
    /**
     * The single string literal a method returns, or null for anything else.
     *
     * @param  class-string  $class
     */
    public function stringLiteral(string $class, string $method): ?string
    {
        $context = resolve(MethodLocator::class)->locate($class, $method);

        if ($context === null) {
            return null;
        }

        $returns = $this->ownReturns($context->method->stmts ?? []);

        if (count($returns) !== 1) {
            return null;
        }

        $expr = $returns[0]->expr;

        return $expr instanceof String_ ? $expr->value : null;
    }

    /**
     * The method's own return statements, excluding those belonging to a nested closure or function.
     *
     * @param  array<Node\Stmt>  $stmts
     * @return list<Return_>
     */
    private function ownReturns(array $stmts): array
    {
        $visitor = new class extends NodeVisitorAbstract
        {
            /** @var list<Return_> */
            public array $returns = [];

            public function enterNode(Node $node): ?int
            {
                if ($node instanceof FunctionLike) {
                    return NodeVisitor::DONT_TRAVERSE_CHILDREN;
                }

                if ($node instanceof Return_) {
                    $this->returns[] = $node;
                }

                return null;
            }
        };

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($stmts);

        return $visitor->returns;
    }
}
