<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\Empty_;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\Isset_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Expr\UnaryPlus;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;

/**
 * Arithmetic, comparison, logical, and unary +/- operators, and `+` as PHP's array union.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class BinaryOpHandler implements ExpressionHandler
{
    use InspectsAstNodes;

    /** An array the union literal reads itself: a literal, a local holding one, or a `$this->method()` call. */
    private const string ARRAY = 'array';

    /** An array whose keys the union literal cannot read from its syntax, joined by the type it publishes. */
    private const string HIDDEN_KEYS = 'hidden-keys';

    /** A number, string or boolean, or an enum. */
    private const string SCALAR = 'scalar';

    /** A value the engine cannot type, which PHP proves an array when the other operand is one. */
    private const string UNTYPED = 'untyped';

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [
            BinaryOp::class,
            BooleanNot::class,
            Instanceof_::class,
            Isset_::class,
            Empty_::class,
            UnaryMinus::class,
            UnaryPlus::class,
        ];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        // Unary +/- always yield a number; non-literal operands are assumed numeric (variable types aren't tracked).
        if ($expr instanceof UnaryMinus || $expr instanceof UnaryPlus) {
            return ['type' => 'number', 'optional' => false];
        }

        if ($expr instanceof BinaryOp\Plus) {
            return $this->resolvePlus($expr, $scope, $engine);
        }

        // Arithmetic always yields a number. Also catches `(int) round(...) / 2`: PHP precedence binds the
        // cast tighter than the division, so the outer node is a BinaryOp\Div rather than a Cast.
        if ($expr instanceof BinaryOp\Minus
            || $expr instanceof BinaryOp\Mul
            || $expr instanceof BinaryOp\Div
            || $expr instanceof BinaryOp\Mod
            || $expr instanceof BinaryOp\Pow
        ) {
            return ['type' => 'number', 'optional' => false];
        }

        if ($expr instanceof BinaryOp\Concat) {
            return ['type' => 'string', 'optional' => false];
        }

        // Comparison, logical, and type-test operators always produce a boolean. PHP's &&/|| return bool,
        // unlike JS — even as a null-guard (`$this->x && $this->x->y`), no false|T union is needed.
        if ($expr instanceof BinaryOp\Identical
            || $expr instanceof BinaryOp\NotIdentical
            || $expr instanceof BinaryOp\Equal
            || $expr instanceof BinaryOp\NotEqual
            || $expr instanceof BinaryOp\Greater
            || $expr instanceof BinaryOp\GreaterOrEqual
            || $expr instanceof BinaryOp\Smaller
            || $expr instanceof BinaryOp\SmallerOrEqual
            || $expr instanceof BinaryOp\BooleanAnd
            || $expr instanceof BinaryOp\BooleanOr
            || $expr instanceof BinaryOp\LogicalAnd
            || $expr instanceof BinaryOp\LogicalOr
            || $expr instanceof BinaryOp\LogicalXor
            || $expr instanceof BooleanNot
            || $expr instanceof Instanceof_
            || $expr instanceof Isset_
            || $expr instanceof Empty_
        ) {
            return ['type' => 'boolean', 'optional' => false];
        }

        // Spaceship comparison produces -1|0|1.
        if ($expr instanceof BinaryOp\Spaceship) {
            return ['type' => 'number', 'optional' => false];
        }

        // Coalesce belongs to CoalesceHandler; other BinaryOp kinds (bitwise, shift) are simply not typed.
        return null;
    }

    /**
     * `+` as arithmetic, or, when an operand is an array, as PHP's array union, typed as the spread literal it equals.
     *
     * @return ValueExpressionResult
     */
    private function resolvePlus(BinaryOp\Plus $plus, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        $bindings = $scope->varValueBindings;

        try {
            [$kind, $union] = $this->boundUnion($plus, $scope, $engine);

            return $kind === self::ARRAY
                ? $engine->resolve($this->arrayUnionLiteral($union, $scope->literalLocalBindings()))
                : ['type' => 'number', 'optional' => false];
        } finally {
            $scope->varValueBindings = $bindings;
        }
    }

    /**
     * A `+`'s kind, with each operand the union literal does not read from its syntax spread as a value bound to its
     * one resolution, so the literal neither resolves it again nor reads its arm by anything but the type it publishes.
     *
     * @return array{string, BinaryOp\Plus}
     */
    private function boundUnion(BinaryOp\Plus $plus, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        // `+` on an array and a number throws, so a number literal settles it before any operand is resolved.
        if ($this->isNumberLiteral($plus->left) || $this->isNumberLiteral($plus->right)) {
            return [self::SCALAR, $plus];
        }

        [$leftKind, $left] = $plus->left instanceof BinaryOp\Plus
            ? $this->boundUnion($plus->left, $scope, $engine)
            : $this->boundOperand($plus->left, $scope, $engine);
        [$rightKind, $right] = $plus->right instanceof BinaryOp\Plus
            ? $this->boundUnion($plus->right, $scope, $engine)
            : $this->boundOperand($plus->right, $scope, $engine);
        $kinds = [$leftKind, $rightKind];

        $kind = match (true) {
            in_array(self::ARRAY, $kinds, true) || in_array(self::HIDDEN_KEYS, $kinds, true) => self::ARRAY,
            in_array(self::SCALAR, $kinds, true) => self::SCALAR,
            default => self::UNTYPED,
        };

        return [$kind, new BinaryOp\Plus($left, $right, $plus->getAttributes())];
    }

    /**
     * One operand's kind, and the expression the union literal spreads for it.
     *
     * @return array{string, Expr}
     */
    private function boundOperand(Expr $operand, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        $local = $operand instanceof Variable && is_string($operand->name) ? ($scope->literalLocalBindings()[$operand->name] ?? null) : null;

        if ($operand instanceof Array_ || $local instanceof Array_) {
            return [self::ARRAY, $operand];
        }

        $result = $engine->resolve($operand);
        $kind = $this->operandKind($result);

        // The literal flattens a `$this->method()` spread from the method's own body, as a resource spread does.
        if ($kind === self::HIDDEN_KEYS && $operand instanceof MethodCall && $this->hasThisReceiver($operand)) {
            return [self::ARRAY, $operand];
        }

        // No PHP variable can be named `+…`, so the binding shadows none.
        $name = '+'.spl_object_id($operand);
        $scope->varValueBindings[$name] = $kind === self::UNTYPED
            ? ['type' => 'Record<string, unknown>', 'optional' => false]
            : $result;

        return [$kind, new Variable($name)];
    }

    /**
     * What one resolved operand holds: a scalar, nothing the engine can type, or an array whose keys stay unread.
     *
     * @param  ValueExpressionResult  $result
     */
    private function operandKind(array $result): string
    {
        $type = ValueResult::stripNullArm($result['type']);
        // An enum's import channel rides a list of the enum too, so only the enum's own token counts as a scalar.
        $enumTokens = array_map(
            ValueResult::enumTypeName(...),
            array_values(array_filter([$result['directEnumFqcn'] ?? null, $result['enumFqcn'] ?? null])),
        );

        return match (true) {
            $type === 'unknown' => self::UNTYPED,
            array_all(
                TsTypeString::splitTopLevelUnion($type),
                fn (string $arm): bool => $this->isScalarType($arm) || in_array($arm, $enumTokens, true),
            ) => self::SCALAR,
            default => self::HIDDEN_KEYS,
        };
    }

    /**
     * Whether one union arm is a JSON scalar: a number, string or boolean, or a literal of one.
     */
    private function isScalarType(string $arm): bool
    {
        return in_array($arm, ['number', 'string', 'boolean', 'true', 'false'], true)
            || is_numeric($arm)
            || (strlen($arm) >= 2 && in_array($arm[0], ['"', "'"], true) && $arm[-1] === $arm[0]);
    }

    /**
     * Whether an operand is an int or float literal, signed or not.
     */
    private function isNumberLiteral(Expr $operand): bool
    {
        $operand = $operand instanceof UnaryMinus || $operand instanceof UnaryPlus ? $operand->expr : $operand;

        return $operand instanceof Int_ || $operand instanceof Float_;
    }
}
