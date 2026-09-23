<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Concerns\BuildsInlineObjectTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Yield_;
use PhpParser\Node\Expr\YieldFrom;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Block;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use ReflectionClass;

/**
 * A method's return type: the declaration first, then, when that is too vague to publish, the shape its
 * literal body spells in place of the declaration's array arm, beside every other arm it names.
 *
 * The rules are in docs/components/receiver-types.md § Following a method's return type. A container
 * singleton, so the re-entrancy guard below is shared by every call site rather than per instance. That
 * guard is deliberate defence-in-depth and changes no result today: AstEngine::analyzeMethod() cuts the
 * same cycle itself, so this one only stops a cycle before it reaches the engine.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @phpstan-type OwnReturn = array{Return_, bool}
 *
 * @internal
 */
final class MethodReturnTypeResolver
{
    use BuildsInlineObjectTypes;

    /** @var array<string, true> class@method bodies currently being analyzed */
    private array $analyzing = [];

    /**
     * Reflect a method's return type; when that is vague or rejected, analyze the method body once.
     *
     * @param  class-string  $class
     * @return ValueExpressionResult|null
     */
    public function resolve(string $class, string $methodName): ?array
    {
        if (! method_exists($class, $methodName)) {
            return null;
        }

        $reflected = resolve(ReflectedTypeAcceptor::class)
            ->accept(LaravelTsPublish::methodOrDocblockReturnTypes(new ReflectionClass($class), $methodName));

        if ($reflected !== null && ! TsTypeString::isVagueTsType($reflected['type'])) {
            return $reflected;
        }

        return $this->bodyType($class, $methodName, $reflected) ?? $reflected;
    }

    /**
     * The inline object a method's literal return body spells, beside every non-array arm its declaration names; null
     * when a return the shape does not describe is left over, or the shape spells nothing importable.
     *
     * @param  class-string  $class
     * @param  ValueExpressionResult|null  $reflected
     * @return ValueExpressionResult|null
     */
    private function bodyType(string $class, string $methodName, ?array $reflected): ?array
    {
        $key = $class.'@'.$methodName;
        $otherArms = $this->nonArrayArms($reflected);
        $body = resolve(MethodLocator::class)->locate($class, $methodName)?->method->stmts;

        if (isset($this->analyzing[$key])
            || $otherArms === null
            || $body === null
            || ! $this->shapeReadsEveryReturn($body, $otherArms)
        ) {
            return null;
        }

        $this->analyzing[$key] = true;

        try {
            $analysis = resolve(AstEngine::class)->analyzeMethod(
                $class,
                $methodName,
                is_a($class, Model::class, true) ? $class : null,
                carriesImports: false,
            );
        } finally {
            unset($this->analyzing[$key]);
        }

        if ($analysis->properties === []) {
            return null;
        }

        $type = $this->buildInlineObjectType($analysis);

        // The inline type carries no FQCN channel, so a token needing an import could never be emitted with one.
        return TsTypeString::shapeValueHasUnimportableToken($type)
            ? null
            : [...ValueResult::unknown(), 'type' => TsTypeString::hoistNull([$type, ...$otherArms])];
    }

    /**
     * The arms of a vague declared return other than its array ones, which the body's shape stands in for; null when
     * one admits any value or names a type the shape could not carry the import of.
     *
     * @param  ValueExpressionResult|null  $reflected
     * @return list<string>|null
     */
    private function nonArrayArms(?array $reflected): ?array
    {
        $arms = [];

        foreach ($reflected === null ? [] : TsTypeString::splitTopLevelUnion($reflected['type']) as $arm) {
            if (TsTypeShape::elementType($arm) !== null || str_starts_with($arm, 'Record<')) {
                continue;
            }

            if ($arm === 'unknown' || TsTypeString::shapeValueHasUnimportableToken($arm)) {
                return null;
            }

            $arms[] = $arm;
        }

        return $arms;
    }

    /**
     * Whether the analysis of this body reads every value it returns, or a declared non-array arm covers what it skips.
     *
     * The analysis reads every array literal that only `if` and loop blocks enclose when one of them has an item,
     * else only the body's first return; a generator returns a Generator, so nothing it returns is read.
     *
     * @param  array<Node\Stmt>  $stmts
     * @param  list<string>  $otherArms
     */
    private function shapeReadsEveryReturn(array $stmts, array $otherArms): bool
    {
        $returns = $this->ownReturns($stmts);

        if ($returns === null) {
            return false;
        }

        $sweeps = array_any(
            $returns,
            fn (array $own): bool => $own[1] && $own[0]->expr instanceof Array_ && $own[0]->expr->items !== [],
        );
        $first = new NodeFinder()->findFirstInstanceOf($stmts, Return_::class);
        $readsAny = false;

        foreach ($returns as [$return, $direct]) {
            $read = $sweeps ? $direct && $return->expr instanceof Array_ : $return === $first;
            $readsAny = $readsAny || $read;

            if ($return->expr === null || (! $read && ! $this->armCovers($return->expr, $otherArms))) {
                return false;
            }
        }

        return $readsAny;
    }

    /**
     * Each of a body's own returns, flagged when only `if` and loop blocks enclose it; null when the body yields.
     *
     * @param  array<Node\Stmt>  $stmts
     * @return list<OwnReturn>|null
     */
    private function ownReturns(array $stmts): ?array
    {
        $visitor = new class extends NodeVisitorAbstract
        {
            /** @var list<array{Return_, bool}> */
            public array $returns = [];

            public bool $yields = false;

            private int $enclosingBlocks = 0;

            /** Record a return with whether a block the branch sweep skips encloses it, and note a yield. */
            public function enterNode(Node $node): ?int
            {
                if ($node instanceof FunctionLike || $node instanceof ClassLike) {
                    return NodeVisitor::DONT_TRAVERSE_CHILDREN;
                }

                $this->yields = $this->yields || $node instanceof Yield_ || $node instanceof YieldFrom;
                $this->enclosingBlocks += $this->hidesReturns($node) ? 1 : 0;

                if ($node instanceof Return_) {
                    $this->returns[] = [$node, $this->enclosingBlocks === 0];
                }

                return null;
            }

            /** Leave a block the branch sweep skips. */
            public function leaveNode(Node $node): ?int
            {
                $this->enclosingBlocks -= $this->hidesReturns($node) ? 1 : 0;

                return null;
            }

            /** Whether the branch sweep leaves the returns inside this block unread. */
            private function hidesReturns(Node $node): bool
            {
                return $node instanceof Switch_
                    || $node instanceof TryCatch
                    || $node instanceof Block
                    || $node instanceof Declare_;
            }
        };

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($stmts);

        return $visitor->yields ? null : $visitor->returns;
    }

    /**
     * Whether a declared non-array arm holds the value a returned `null`, boolean, string or number literal is.
     *
     * @param  list<string>  $otherArms
     */
    private function armCovers(Expr $returned, array $otherArms): bool
    {
        $constant = $returned instanceof ConstFetch ? $returned->name->toLowerString() : null;

        $literal = match (true) {
            in_array($constant, ['null', 'true', 'false'], true) => $constant,
            $returned instanceof String_ => 'string',
            $returned instanceof Int_, $returned instanceof Float_ => 'number',
            default => null,
        };

        return match ($literal) {
            'null', 'string', 'number' => in_array($literal, $otherArms, true),
            'true', 'false' => in_array($literal, $otherArms, true) || in_array('boolean', $otherArms, true),
            default => false,
        };
    }
}
