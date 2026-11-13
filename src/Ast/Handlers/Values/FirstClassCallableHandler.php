<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers\Values;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallableForms;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\KeepsHeldObjects;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeString;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;

/**
 * A first-class callable, such as `strlen(...)` or `$this->label(...)`, is a Closure, never a call: json_encode()
 * writes it as `{}`. Where the caller invokes every callable it holds, as Inertia does, it types as that call.
 * CallableForms holds the forms a callable takes where its caller calls it.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class FirstClassCallableHandler implements ExpressionHandler, KeepsHeldObjects
{
    /** Whether the subject's caller calls each callable value before it encodes it, as Inertia does. */
    public function __construct(private readonly bool $invoked = false) {}

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [FuncCall::class, MethodCall::class, StaticCall::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if (! CallableForms::isFirstClassCallable($expr)) {
            return null;
        }

        if ($this->invoked) {
            return ValueResult::heldObject($engine->resolve(CallableForms::invokedCall($expr)));
        }

        $this->warnOfUncalledCallable($expr, $scope);

        return ValueResult::heldObject(['type' => TsTypeString::EMPTY_OBJECT, 'optional' => false]);
    }

    /**
     * Warn that a first-class callable creates a Closure where nothing calls it, so a call was almost certainly meant.
     */
    private function warnOfUncalledCallable(Expr $expr, AnalysisScope $scope): void
    {
        AnalysisWarnings::addOnce($scope->subjectReflection->getName(), sprintf(
            'A first-class callable on line %d creates a Closure instead of calling the method; nothing in this '
            .'position calls it. Call the method instead.',
            $expr->getStartLine(),
        ));
    }
}
