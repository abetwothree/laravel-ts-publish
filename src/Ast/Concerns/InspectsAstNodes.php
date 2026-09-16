<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
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
 * Node-shape questions about a parsed expression: what it is, what it names, what it returns.
 *
 * Every member here answers from the AST alone, with no notion of a resource, so the engine layer owns
 * it. The resource semantics it was split from live in `Analyzers\Concerns\InspectsResourceCalls`, which
 * requires its host to also use this trait.
 *
 * @internal
 */
trait InspectsAstNodes
{
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
     * An int key is a real JSON object key: `[1 => 'Basic']` encodes as `{"1":"Basic"}`, not as a list.
     *
     * @param  ReflectionClass<object>  $subject  resolves `self`, `static` and `parent` in a constant key
     */
    protected function resolveKeyName(Expr $key, ReflectionClass $subject): ?string
    {
        if ($key instanceof String_) {
            return $key->value;
        }

        if ($key instanceof Int_) {
            return $this->publishableKeyName((string) $key->value, $subject);
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

            return is_int($value) || is_string($value) ? $this->publishableKeyName((string) $value, $subject) : null;
        }

        return null;
    }

    /**
     * Drop a numeric key when the analyzed subject is a resource, and keep every other one.
     *
     * A published member name cannot be numeric: PHP stores a numeric string array key as an int, so it
     * would arrive as an `int` in the transformer's `array<string, …>` property maps. The test is the
     * subject, not the position, so a numeric key nested inside a resource is dropped as well.
     *
     * @param  ReflectionClass<object>  $subject
     */
    private function publishableKeyName(string $name, ReflectionClass $subject): ?string
    {
        return is_numeric($name) && $subject->isSubclassOf(JsonResource::class) ? null : $name;
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
     * @param  array<string, Expr>  $localVarBindings  substituted for a variable argument when supplied
     */
    protected function mergedArrayLiteral(FuncCall $call, ?string $parentMethodName = null, array $localVarBindings = []): ?Array_
    {
        if (! $call->name instanceof Name || $call->name->getLast() !== 'array_merge' || $call->isFirstClassCallable()) {
            return null;
        }

        $items = [];

        foreach ($call->getArgs() as $arg) {
            $value = $arg->value;

            if ($value instanceof Variable && is_string($value->name)) {
                $value = $localVarBindings[$value->name] ?? $value;
            }

            if ($value instanceof Array_) {
                $items = [...$items, ...$value->items];

                continue;
            }

            if (! $this->isParentCallTo($value, $parentMethodName)) {
                return null;
            }

            $items[] = new ArrayItem($value, byRef: false, unpack: true);
        }

        return new Array_($items);
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
                || $stmt instanceof While_) {
                $returns = [...$returns, ...$this->collectReturnExpressions($stmt->stmts)];

                continue;
            }

            if ($stmt instanceof Do_) {
                $returns = [...$returns, ...$this->collectReturnExpressions($stmt->stmts)];
            }
        }

        return $returns;
    }
}
