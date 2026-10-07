<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
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
use PhpParser\Node\Stmt\Return_;

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

    /** Every name-keyed binding table, empty: a helper's body is a function scope no caller's name reaches. */
    private const array EMPTY_NAME_BINDINGS = [
        'closureParamExprBindings' => [],
        'varClassBindings' => [],
        'varGuardBindings' => [],
        'varDocBindings' => [],
        'varModelBindings' => [],
        'varCollectionBindings' => [],
        'varValueBindings' => [],
        'localVarBindings' => [],
        'requestVarNames' => [],
        'claimedClosures' => [],
    ];

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
        $stmts = resolve(MethodLocator::class)->locate($subject->getName(), $name)?->method->stmts ?? [];
        $returns = $this->collectReturnExpressions($stmts);

        // Only a wrap holds what no declared return can name, so any other helper's body is left unread.
        if (! array_all($returns, $this->wrapsEnumOrIsNull(...)) || array_all($returns, $this->isNullLiteral(...))) {
            return null;
        }

        // An untyped helper that runs off its end returns a null no return shows; a declared return type throws there.
        if (! $method->hasReturnType() && ! end($stmts) instanceof Return_) {
            return null;
        }

        $bindings = $scope->nameBindings();
        $resolvingLocalVars = $scope->resolvingLocalVars;
        $declaringFileClass = $scope->declaringFileClass;
        $relationModel = $scope->closureRelationModelClass;

        try {
            $scope->visitedSpreadMethods[$name] = true;
            $scope->restoreNameBindings(self::EMPTY_NAME_BINDINGS);
            $scope->resolvingLocalVars = [];
            // A relation closure's model answers an unbound `$variable->prop`, but the helper's body reads the subject.
            $scope->closureRelationModelClass = null;
            $scope->declaringFileClass = LaravelTsPublish::methodDeclaringFileClass($method);
            $this->collectLocalVarBindings($stmts, $scope);

            $result = ValueResult::analyzeClosureUnion($returns, $engine, $scope);
        } finally {
            $scope->restoreNameBindings($bindings);
            $scope->resolvingLocalVars = $resolvingLocalVars;
            $scope->declaringFileClass = $declaringFileClass;
            $scope->closureRelationModelClass = $relationModel;
            unset($scope->visitedSpreadMethods[$name]);
        }

        // Without an enum channel no wrap was typed, and the `null` a dropped wrap leaves says nothing about it.
        return isset($result['enumFqcn']) || isset($result['multiEnumResourceFqcns']) ? $result : null;
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
