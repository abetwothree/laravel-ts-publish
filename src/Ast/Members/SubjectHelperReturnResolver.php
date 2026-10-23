<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Members;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallSite;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ParameterBinder;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

/**
 * The value a subject's own `$this->m()` helper returns when every return wraps an enum in an `EnumResource` or is
 * `null`: no declared return type names the enum a wrap holds, so the helper's body is read in its own bindings.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class SubjectHelperReturnResolver
{
    use CollectsLocalVarBindings;
    use InspectsAstNodes;

    /**
     * The union of a `$this->m()` helper's returns, or null for any other call or body, or a union that types no wrap.
     *
     * @return ValueExpressionResult|null
     */
    public function resolveEnumResourceReturn(MethodCall $call, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        // Without imports the body fallback would drop its whole shape for the enum a wrap names.
        if (! $scope->carriesImports
            || ! $this->hasThisReceiver($call)
            || ! $call->name instanceof Identifier
            || $call->isFirstClassCallable()
        ) {
            return null;
        }

        $name = $call->name->toString();
        $subject = $scope->subjectReflection;

        if (! $subject->hasMethod($name) || isset($scope->visitedSpreadMethods[$name])) {
            return null;
        }

        $method = $subject->getMethod($name);
        $declaration = resolve(MethodLocator::class)->locate($subject->getName(), $name)?->method;
        $stmts = $declaration->stmts ?? [];
        $returns = $this->collectReturnExpressions($stmts);

        // Only a wrap holds what no declared return can name; MethodReturnTypeResolver types any other helper.
        if ($declaration === null
            || ! array_all($returns, $this->wrapsEnumOrIsNull(...))
            || array_all($returns, $this->isNullLiteral(...))
        ) {
            return null;
        }

        // Read in the caller's names, before they are set aside.
        $passed = new CallSite($call, $scope, $engine)->passedTo($method, valueRead: true);
        $proofs = $this->proofsAcrossCall($scope, $call);
        $rewritten = $this->rewrittenThisPaths($stmts, $scope);

        $result = $scope->within(function () use ($scope, $name, $method, $proofs, $rewritten, $passed, $stmts): void {
            $scope->enterSubjectMethod($name, LaravelTsPublish::methodDeclaringFileClass($method), $proofs, $rewritten);
            resolve(ParameterBinder::class)->bindCall($method, $passed, $scope);
            $this->seedBody($stmts, $scope);
        }, fn (): array => ValueResult::analyzeClosureUnion($returns, $engine, $scope));

        // Without an enum channel no wrap was typed, and the `null` a dropped wrap leaves says nothing about it.
        if (! isset($result['enumFqcn']) && ! isset($result['multiEnumResourceFqcns'])) {
            return null;
        }

        if ($this->canEndWithoutValue($declaration)) {
            $result['type'] = ValueResult::withNullArm($result['type']);
        }

        return $result;
    }

    /**
     * Whether a returned expression is a literal `null`, constructs an `EnumResource`, or is a ternary of those.
     */
    private function wrapsEnumOrIsNull(Expr $returned): bool
    {
        if ($returned instanceof Ternary) {
            return $returned->if !== null && $this->wrapsEnumOrIsNull($returned->if) && $this->wrapsEnumOrIsNull($returned->else);
        }

        if ($this->isNullLiteral($returned)) {
            return true;
        }

        if ($returned instanceof New_) {
            return $returned->class instanceof Name && is_a($returned->class->toString(), EnumResource::class, true);
        }

        return $returned instanceof StaticCall
            && $returned->class instanceof Name
            && $returned->name instanceof Identifier
            && in_array($returned->name->toString(), ['make', 'collection'], true)
            && is_a($returned->class->toString(), EnumResource::class, true);
    }

    /**
     * Whether an expression is the literal `null`.
     */
    private function isNullLiteral(Expr $expr): bool
    {
        return $expr instanceof ConstFetch && $expr->name->toLowerString() === 'null';
    }
}
