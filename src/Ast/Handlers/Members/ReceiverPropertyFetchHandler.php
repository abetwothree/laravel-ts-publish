<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers\Members;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\ReturnLiteralReader;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverMemberReader;
use AbeTwoThree\LaravelTsPublish\Ast\Values\EnumValueTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use PhpParser\ConstExprEvaluationException;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\MatchArm;
use UnitEnum;

/**
 * `<receiver>->prop` and `<receiver>?->prop` typed from the property on the receiver's PHP class, or off an enum case
 * itself, or one a helper naming no class returns, as the literal it sends; the rules are in
 * docs/components/receiver-types.md § Property access on a receiver.
 *
 * Registered immediately before ReceiverMethodCallHandler, so every specific handler keeps priority.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class ReceiverPropertyFetchHandler implements ExpressionHandler
{
    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [PropertyFetch::class, NullsafePropertyFetch::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if (! ($expr instanceof PropertyFetch || $expr instanceof NullsafePropertyFetch) || ! $expr->name instanceof Identifier) {
            return null;
        }

        // A `$this->prop` leaf is ThisPropertyHandler's, which reads the subject's own declaration first.
        if ($expr->var instanceof Variable && $expr->var->name === 'this') {
            return null;
        }

        $name = $expr->name->toString();
        $caseMember = $this->caseMember($expr->var, $name, $scope);
        $receivers = resolve(ReceiverClassResolver::class);
        $receiver = $caseMember === null ? $receivers->resolve($expr->var, $scope) : null;

        // A helper declared to return a class keeps that class's member; only one naming none is read by its body.
        if ($caseMember === null && $receiver === null) {
            $caseMember = $this->helperCaseMember($expr->var, $name, $scope);
        }

        if ($caseMember !== null) {
            return $expr instanceof NullsafePropertyFetch
                ? [...$caseMember, 'type' => TsTypeString::withNullArm($caseMember['type'])]
                : $caseMember;
        }

        if ($receiver === null) {
            return null;
        }

        $merged = ReceiverMemberReader::value($receiver, $name, $scope);

        if ($merged === null) {
            return null;
        }

        $merged = $receivers->warnOfLeftOut($receiver, $merged, $scope, $engine);

        if ($expr instanceof NullsafePropertyFetch || $receiver->shortCircuits) {
            $merged['type'] = TsTypeString::withNullArm($merged['type']);
        }

        return $merged;
    }

    /**
     * A member read off cases themselves: a case, or a ternary or `match` whose every arm is one, `null` or a
     * `throw`, each case sending its own literal; null for any other receiver, a member a case lacks, or no case.
     *
     * @return ValueExpressionResult|null
     */
    private function caseMember(Expr $receiver, string $name, AnalysisScope $scope): ?array
    {
        $arms = match (true) {
            $receiver instanceof Ternary => [$receiver->if ?? $receiver->cond, $receiver->else],
            $receiver instanceof Match_ => array_map(fn (MatchArm $arm): Expr => $arm->body, $receiver->arms),
            default => null,
        };

        return $arms === null
            ? $this->ownCaseMember($receiver, $name, $scope)
            : $this->armsCaseMember($arms, $name, $scope);
    }

    /**
     * A member read off what a `$this->m()`, `self::m()`, `static::m()` or `parent::m()` helper returns, each returned
     * expression an arm whose cases resolve in the class declaring the helper; null for any other receiver or an
     * unlisted body.
     *
     * @return ValueExpressionResult|null
     */
    private function helperCaseMember(Expr $receiver, string $name, AnalysisScope $scope): ?array
    {
        if (! ($receiver instanceof MethodCall || $receiver instanceof StaticCall)
            || ! $receiver->name instanceof Identifier
            || $receiver->isFirstClassCallable()
        ) {
            return null;
        }

        $method = $receiver->name->toString();
        $owner = match (true) {
            $receiver instanceof StaticCall => $scope->staticCallClass($receiver),
            $receiver->var instanceof Variable && $receiver->var->name === 'this' => $scope->thisMemberClass($method, true),
            default => null,
        };

        if ($owner === null || ! $owner->hasMethod($method)) {
            return null;
        }

        $returned = resolve(ReturnLiteralReader::class)->returnedExpressions($owner->getName(), $method);

        if ($returned === null) {
            return null;
        }

        $declaring = AnalysisScope::detached($owner->getMethod($method)->getDeclaringClass(), $scope->runtime);

        return $this->armsCaseMember($returned, $name, $declaring);
    }

    /**
     * The member the cases of every arm but a `null` or a `throw` send, joined; null where an arm holds no case.
     *
     * @param  array<Expr>  $arms
     * @return ValueExpressionResult|null
     */
    private function armsCaseMember(array $arms, string $name, AnalysisScope $scope): ?array
    {
        $results = [];

        foreach ($arms as $arm) {
            // A `throw` arm sends nothing, and a `null` one is skipped as ReceiverClassResolver skips it: only `?->`
            // adds its null.
            if ($arm instanceof Throw_ || ($arm instanceof ConstFetch && $arm->name->toLowerString() === 'null')) {
                continue;
            }

            $result = $this->caseMember($arm, $name, $scope);

            if ($result === null) {
                return null;
            }

            $results[] = $result;
        }

        return $results === [] ? null : ValueResult::unionResults($results);
    }

    /**
     * The member one `E::Case` sends, read off the case its constant expression evaluates to.
     *
     * @return ValueExpressionResult|null
     */
    private function ownCaseMember(Expr $receiver, string $name, AnalysisScope $scope): ?array
    {
        if (! $receiver instanceof ClassConstFetch) {
            return null;
        }

        try {
            $case = resolve(ValueResolver::class)->evaluateConstantExpression($receiver, $scope);
        } catch (ConstExprEvaluationException) {
            return null;
        }

        return $case instanceof UnitEnum ? EnumValueTypes::caseMember($case, $name) : null;
    }
}
