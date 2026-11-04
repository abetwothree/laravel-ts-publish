<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
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
     * One literal for each write a local operand's read may see, joined.
     *
     * @return ValueExpressionResult
     */
    private function resolvePlus(BinaryOp\Plus $plus, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        // The operands bind as the read resolves them, so there is nothing to enter.
        return $scope->within(static function (): void {}, function () use ($plus, $scope, $engine): array {
            [$kind, $unions] = $this->boundUnion($plus, $scope, $engine);

            if ($kind !== self::ARRAY) {
                return ['type' => 'number', 'optional' => false];
            }

            $results = array_map(
                fn (BinaryOp\Plus $union): array => $engine->resolve($this->arrayUnionLiteral($union)),
                $unions,
            );

            return count($results) === 1 ? $results[0] : ValueResult::unionResults($results);
        });
    }

    /**
     * A `+`'s kind, and the unions it may be, one for each write a local operand's read sees.
     *
     * Each operand the union literal does not read from its syntax is spread as a value bound to its one resolution,
     * so the literal neither resolves it again nor reads its arm by anything but the type it publishes.
     *
     * @return array{string, non-empty-list<BinaryOp\Plus>}
     */
    private function boundUnion(BinaryOp\Plus $plus, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        // `+` on an array and a number throws, so a number literal settles it before any operand is resolved.
        if ($this->isNumberLiteral($plus->left) || $this->isNumberLiteral($plus->right)) {
            return [self::SCALAR, [$plus]];
        }

        [$leftKind, $lefts] = $this->boundSide($plus->left, $scope, $engine);
        [$rightKind, $rights] = $this->boundSide($plus->right, $scope, $engine);

        // Past the cap a side that may be several literals is read as its one value instead.
        if (count($lefts) * count($rights) > self::MAX_SPELLED_LITERALS) {
            [$leftKind, $lefts] = count($lefts) === 1
                ? [$leftKind, $lefts]
                : $this->valueOperand($plus->left, $scope, $engine);
            [$rightKind, $rights] = count($rights) === 1
                ? [$rightKind, $rights]
                : $this->valueOperand($plus->right, $scope, $engine);
        }

        $kinds = [$leftKind, $rightKind];

        $kind = match (true) {
            in_array(self::ARRAY, $kinds, true) || in_array(self::HIDDEN_KEYS, $kinds, true) => self::ARRAY,
            in_array(self::SCALAR, $kinds, true) => self::SCALAR,
            default => self::UNTYPED,
        };
        $unions = [];

        foreach ($lefts as $left) {
            foreach ($rights as $right) {
                $unions[] = new BinaryOp\Plus($left, $right, $plus->getAttributes());
            }
        }

        return [$kind, $unions];
    }

    /**
     * One side of a `+`: a nested `+`'s unions, or an operand's.
     *
     * @return array{string, non-empty-list<Expr>}
     */
    private function boundSide(Expr $side, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        return $side instanceof BinaryOp\Plus
            ? $this->boundUnion($side, $scope, $engine)
            : $this->boundOperand($side, $scope, $engine);
    }

    /**
     * One operand's kind, and the expressions the union literal spreads for it.
     *
     * A literal, each literal write a local's read sees, or one value.
     *
     * @return array{string, non-empty-list<Expr>}
     */
    private function boundOperand(Expr $operand, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        $writes = $operand instanceof Array_ ? [$operand] : $this->spelledWrites($operand, $scope);

        if ($writes !== null && array_all($writes, fn (Expr $write): bool => $write instanceof Array_)) {
            return [self::ARRAY, $writes];
        }

        return $this->valueOperand($operand, $scope, $engine);
    }

    /**
     * One operand's kind, and the one expression the union literal spreads for its value.
     *
     * @return array{string, non-empty-list<Expr>}
     */
    private function valueOperand(Expr $operand, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        $result = $engine->resolve($operand);
        $kind = $this->operandKind($result);

        // The literal flattens a `$this->method()` spread from the method's own body, as a resource spread does.
        if ($kind === self::HIDDEN_KEYS && $operand instanceof MethodCall && $this->hasThisReceiver($operand)) {
            return [self::ARRAY, [$operand]];
        }

        // No PHP variable can be named `+…`, so the binding shadows none.
        $name = '+'.spl_object_id($operand);
        $scope->varValueBindings[$name] = $kind === self::UNTYPED
            ? ['type' => 'Record<string, unknown>', 'optional' => false]
            : $result;

        return [$kind, [new Variable($name)]];
    }

    /**
     * What one resolved operand holds: a scalar, nothing the engine can type, or an array whose keys stay unread.
     *
     * @param  ValueExpressionResult  $result
     */
    private function operandKind(array $result): string
    {
        $type = TsTypeString::withoutNullArm($result['type']);
        // An enum's import channel rides a list of the enum too, so only the enum's own token counts as a scalar.
        $enumTokens = array_map(
            ValueResult::enumTypeName(...),
            array_values(array_filter([$result['directEnumFqcn'] ?? null, $result['enumFqcn'] ?? null])),
        );

        return match (true) {
            $type === 'unknown' => self::UNTYPED,
            array_all(
                TsTypeString::splitTopLevelUnion($type),
                fn (string $arm): bool => TsTypeShape::isScalar($arm) || in_array($arm, $enumTokens, true),
            ) => self::SCALAR,
            default => self::HIDDEN_KEYS,
        };
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
