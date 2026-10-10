<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Support\IndexSignatureKey;
use AbeTwoThree\LaravelTsPublish\Support\PhpArrayKey;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\BinaryOp\Plus;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Block;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\Node\Stmt\While_;
use ReflectionClass;

/**
 * Node-shape questions about a parsed expression: what it is, what it names, what it returns. The engine layer owns
 * it; the resource semantics it was split from live in `Analyzers\Concerns\InspectsResourceCalls`, which requires its
 * host to also use this trait.
 *
 * @internal
 */
trait InspectsAstNodes
{
    /**
     * The int PHP stores a literal key as, a numeric string such as `'6'` included, or null for any other key.
     */
    protected static function storedIntKey(Expr $key): ?int
    {
        return match (true) {
            $key instanceof Int_ => $key->value,
            $key instanceof String_ => PhpArrayKey::storedInt($key->value),
            default => null,
        };
    }

    /**
     * Whether an expression is `$this->{$methodName}(...)`, called directly on `$this`.
     */
    protected function isThisMethodCall(Expr $expr, string $methodName): bool
    {
        return $expr instanceof MethodCall
            && $this->hasThisReceiver($expr)
            && $expr->name instanceof Identifier
            && $expr->name->toString() === $methodName;
    }

    /**
     * Check a method call's receiver is `$this`, regardless of which method is being called. A call
     * chained off anything else (`$this->helper()->method()`, a variable, a property) must not match.
     */
    protected function hasThisReceiver(MethodCall $call): bool
    {
        return $call->var instanceof Variable && $call->var->name === 'this';
    }

    /**
     * Whether an expression is a `$this->prop` property fetch, whichever property it names.
     */
    protected function isThisPropertyFetch(Expr $expr): bool
    {
        return $expr instanceof PropertyFetch
            && $expr->var instanceof Variable
            && $expr->var->name === 'this';
    }

    /**
     * Whether an expression is the `$this->resource` a JsonResource wraps its model in.
     */
    protected function isResourceFetch(Expr $expr): bool
    {
        return $expr instanceof PropertyFetch
            && $expr->var instanceof Variable
            && $expr->var->name === 'this'
            && $expr->name instanceof Identifier
            && $expr->name->toString() === 'resource';
    }

    /**
     * The name an array key spells, or null when it is not a literal this can read.
     *
     * An int key is named as `json_encode()` writes it, `"1"`, whatever the subject: only a resource publisher
     * re-indexes an array whose keys are all numeric. A key built from literal text around a value is named by the
     * template-literal index signature that covers it.
     *
     * @param  ReflectionClass<object>  $subject  resolves `self`, `static` and `parent` in a constant key
     */
    protected function resolveKeyName(Expr $key, ReflectionClass $subject): ?string
    {
        if ($key instanceof String_) {
            return $this->literalKeyName($key->value, $subject);
        }

        if ($key instanceof Int_) {
            return (string) $key->value;
        }

        if ($key instanceof ClassConstFetch && $key->class instanceof Name && $key->name instanceof Identifier) {
            $parent = $subject->getParentClass();
            $class = match ($key->class->toLowerString()) {
                'self', 'static' => $subject->getName(),
                'parent' => $parent === false ? null : $parent->getName(),
                default => $key->class->toString(),
            };
            $constant = $class !== null ? $class.'::'.$key->name->toString() : null;
            $value = $constant !== null && defined($constant) ? constant($constant) : null;
            $value = is_string($value) ? $this->literalKeyName($value, $subject) : $value;

            return is_int($value) || is_string($value) ? (string) $value : null;
        }

        return $this->interpolatedKeyName($key);
    }

    /**
     * A literal key's text, or null with a warning when it reads as an index signature: printed bare, it would type
     * other keys.
     *
     * @param  ReflectionClass<object>  $subject  the warning's subject
     */
    protected function literalKeyName(string $text, ReflectionClass $subject): ?string
    {
        if (! IndexSignatureKey::is($text)) {
            return $text;
        }

        AnalysisWarnings::addOnce(
            $subject->getName(),
            'The key "'.$text.'" reads as an index signature, so it is left out; rename it.',
        );

        return null;
    }

    /**
     * An index-signature name for a key built from literal text around a variable, `"{$name}_tag"` or
     * `$name.'_tag'`, or null for any other key.
     */
    protected function interpolatedKeyName(Expr $key): ?string
    {
        $parts = match (true) {
            $key instanceof InterpolatedString => $key->parts,
            $key instanceof Concat => [$key->left, $key->right],
            default => null,
        };

        return $parts === null ? null : IndexSignatureKey::fromParts(array_values(array_map(
            fn (Node $part): ?string => $part instanceof InterpolatedStringPart || $part instanceof String_ ? $part->value : null,
            $parts,
        )));
    }

    /**
     * The class name a static call is made on, or null when the class is an expression.
     */
    protected function resolveStaticCallClassName(StaticCall $call): ?string
    {
        if ($call->class instanceof Name) {
            return $call->class->toString();
        }

        return null; // @codeCoverageIgnore
    }

    /**
     * Re-express an `array_merge(...)` call as the one array literal it evaluates to, or null when an
     * argument hides keys that cannot be read statically. One literal, not one analysis per argument:
     * only a single walk lets a later key clear the FQCN channels an earlier one registered.
     *
     * array_merge() renumbers every int key from 0, so a literal int key takes its new number until a spread or
     * `parent::` call, whose int keys cannot be counted.
     *
     * @param  array<string, Expr>  $localVarBindings  substituted for a variable argument when supplied
     */
    protected function mergedArrayLiteral(FuncCall $call, ?string $parentMethodName = null, array $localVarBindings = []): ?Array_
    {
        if (! $call->name instanceof Name || $call->name->getLast() !== 'array_merge' || $call->isFirstClassCallable()) {
            return null;
        }

        $items = [];
        $keyless = [];
        $next = 0;

        foreach ($call->getArgs() as $arg) {
            $value = $arg->value;

            if ($value instanceof Variable && is_string($value->name)) {
                $value = $localVarBindings[$value->name] ?? $value;
            }

            if ($value instanceof Array_) {
                foreach ($value->items as $item) {
                    if ($next !== null && $item->key !== null && self::storedIntKey($item->key) !== null) {
                        $items[] = new ArrayItem($item->value, new Int_($next++), $item->byRef, $item->getAttributes());

                        continue;
                    }

                    if ($next !== null && $item->key === null && ! $item->unpack) {
                        $keyless[count($items)] = new ArrayItem($item->value, new Int_($next), $item->byRef, $item->getAttributes());
                    }

                    $next = match (true) {
                        $next === null || $item->unpack => null,
                        $item->key === null => $next + 1,
                        default => $next,
                    };
                    $items[] = $item;
                }

                continue;
            }

            if (! $this->isParentCallTo($value, $parentMethodName)) {
                return null;
            }

            $items[] = new ArrayItem($value, byRef: false, unpack: true);
            $next = null;
        }

        // A keyless item keeps the key it gets at runtime only beside a keyed one; an all-keyless literal stays a list.
        if ($keyless !== [] && array_filter($items, fn (ArrayItem $item): bool => $item->key !== null) !== []) {
            $items = array_values(array_replace($items, $keyless));
        }

        return new Array_($items);
    }

    /**
     * Re-express PHP's array union `$left + $right` as one literal. A literal operand, or a local holding one, gives
     * its items; a nested `+` its own; anything else one spread.
     *
     * With every key a literal, the items run as PHP builds them: the left's, then each right one under a key the left
     * lacks, a keyless item under the int key PHP gives it.
     *
     * A spread hides its keys, so the literal is then `[...$right, ...$left]`, whose later spread wins a key as the
     * left operand does, though an int key can land out of PHP's order.
     *
     * @param  array<string, Expr>  $localVarBindings  substituted for a variable operand
     */
    protected function arrayUnionLiteral(Plus $union, array $localVarBindings = []): Array_
    {
        $left = $this->unionOperandItems($union->left, $localVarBindings);
        $right = $this->unionOperandItems($union->right, $localVarBindings);
        $storedLeft = $this->itemsByStoredKey($left);
        $storedRight = $this->itemsByStoredKey($right);

        return new Array_($storedLeft === null || $storedRight === null
            ? [...$right, ...$left]
            : array_values($storedLeft + $storedRight));
    }

    /**
     * Check if an expression is a `parent::{$methodName}()` call, or any parent:: call when null.
     */
    protected function isParentCallTo(Expr $expr, ?string $methodName = null): bool
    {
        return $expr instanceof StaticCall
            && $expr->class instanceof Name
            && $expr->class->toLowerString() === 'parent'
            && $expr->name instanceof Identifier
            && ($methodName === null || $expr->name->toString() === $methodName);
    }

    /**
     * Check if an expression is a parent::toArray() call.
     */
    protected function isParentToArrayCall(Expr $expr): bool
    {
        return $this->isParentCallTo($expr, 'toArray');
    }

    /**
     * Collect one expression per return path of a closure or arrow function, for building union types.
     *
     * @return list<Expr>
     */
    protected function resolveClosureReturnExpressions(Expr $expr): array
    {
        if ($expr instanceof ArrowFunction) {
            return [$expr->expr];
        }

        if ($expr instanceof ClosureExpr) {
            return $this->collectReturnExpressions($expr->stmts);
        }

        return [];
    }

    /**
     * Whether a closure/arrow function declares more required parameters than Laravel will supply it.
     *
     * Most of the conditional family invokes its default via value($default) — zero arguments — so the
     * default parameter is $providedArgs = 0. The global transform() helper is the one exception: it
     * invokes its default via $default($value), one argument, so its caller passes $providedArgs = 1.
     * Either way, a required parameter beyond that count throws ArgumentCountError instead of a value.
     */
    protected function closureRequiresArguments(Expr $expr, int $providedArgs = 0): bool
    {
        if (! $expr instanceof ClosureExpr && ! $expr instanceof ArrowFunction) {
            return false;
        }

        $requiredParams = 0;

        foreach ($expr->params as $param) {
            if ($param->default === null && ! $param->variadic) {
                $requiredParams++;
            }
        }

        return $requiredParams > $providedArgs;
    }

    /**
     * Recursively collect Return_ expressions, descending into control-flow blocks but not nested closures.
     *
     * @param  array<Stmt>  $stmts
     * @return list<Expr>
     */
    protected function collectReturnExpressions(array $stmts): array
    {
        /** @var list<Expr> $returns */
        $returns = [];

        foreach ($stmts as $stmt) {
            if ($stmt instanceof Return_ && $stmt->expr !== null) {
                $returns[] = $stmt->expr;

                continue;
            }

            if ($stmt instanceof If_) {
                $returns = [...$returns, ...$this->collectReturnExpressions($stmt->stmts)];

                foreach ($stmt->elseifs as $elseif) {
                    $returns = [...$returns, ...$this->collectReturnExpressions($elseif->stmts)];
                }

                if ($stmt->else !== null) {
                    $returns = [...$returns, ...$this->collectReturnExpressions($stmt->else->stmts)];
                }

                continue;
            }

            if ($stmt instanceof Switch_) {
                foreach ($stmt->cases as $case) {
                    $returns = [...$returns, ...$this->collectReturnExpressions($case->stmts)];
                }

                continue;
            }

            if ($stmt instanceof TryCatch) {
                $returns = [...$returns, ...$this->collectReturnExpressions($stmt->stmts)];

                foreach ($stmt->catches as $catch) {
                    $returns = [...$returns, ...$this->collectReturnExpressions($catch->stmts)];
                }

                if ($stmt->finally !== null) {
                    $returns = [...$returns, ...$this->collectReturnExpressions($stmt->finally->stmts)];
                }

                continue;
            }

            if ($stmt instanceof Foreach_
                || $stmt instanceof For_
                || $stmt instanceof While_
                || $stmt instanceof Block) {
                $returns = [...$returns, ...$this->collectReturnExpressions($stmt->stmts)];

                continue;
            }

            if ($stmt instanceof Do_) {
                $returns = [...$returns, ...$this->collectReturnExpressions($stmt->stmts)];
            }
        }

        return $returns;
    }

    /**
     * The items one operand of `+` gives the union literal.
     *
     * @param  array<string, Expr>  $localVarBindings
     * @return list<ArrayItem>
     */
    private function unionOperandItems(Expr $operand, array $localVarBindings): array
    {
        if ($operand instanceof Variable && is_string($operand->name)) {
            $operand = $localVarBindings[$operand->name] ?? $operand;
        }

        return match (true) {
            $operand instanceof Array_ => array_values($operand->items),
            $operand instanceof Plus => array_values($this->arrayUnionLiteral($operand, $localVarBindings)->items),
            default => [new ArrayItem($operand, byRef: false, unpack: true)],
        };
    }

    /**
     * A literal's items as PHP stores them: a key written twice keeps its first place and its last value, and a keyless
     * item takes its int key; null when an item is a spread or its key is no literal.
     *
     * @param  list<ArrayItem>  $items
     * @return array<array-key, ArrayItem>|null
     */
    private function itemsByStoredKey(array $items): ?array
    {
        $stored = [];
        $next = null;

        foreach ($items as $item) {
            $key = match (true) {
                $item->key === null => $next ?? 0,
                $item->key instanceof Int_ => $item->key->value,
                $item->key instanceof String_ => self::storedIntKey($item->key) ?? $item->key->value,
                default => null,
            };

            if ($key === null || $item->unpack) {
                return null;
            }

            $stored[$key] = $item->key === null
                ? new ArrayItem($item->value, new Int_($key), $item->byRef, $item->getAttributes())
                : $item;

            if (is_int($key)) {
                $next = max($next ?? $key + 1, $key + 1);
            }
        }

        return $stored;
    }
}
