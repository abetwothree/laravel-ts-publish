<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Variants;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\ReadPath;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResolver;
use PhpParser\ConstExprEvaluationException;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\BooleanAnd;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\LogicalAnd;
use PhpParser\Node\Expr\BinaryOp\LogicalOr;
use PhpParser\Node\Expr\BinaryOp\LogicalXor;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\Empty_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Isset_;
use PhpParser\Node\Name;
use ReflectionFunction;
use UnitEnum;

/**
 * Whether a guard holds when one read sends one value, computed by PHP's own operators on the concrete values: the
 * read, its `->value` or `->name`, and constants, joined by `!`, `&&`, `||`, `xor`, `===`, `!==`, `==`, `!=`,
 * `is_null()`, `isset()`, `empty()` and `in_array()` over a constant list. Anything else leaves the guard undecided.
 *
 * @internal
 */
final readonly class ConditionTruth
{
    /** The functions a guard may call, whose answer depends on their arguments alone. */
    private const array FUNCTIONS = ['is_null', 'in_array'];

    /**
     * Decide guards for one value of one read.
     */
    private function __construct(
        private ReadPath $read,
        private DiscriminantValue $value,
        private AnalysisScope $scope,
        private ValueResolver $values,
    ) {}

    /**
     * Whether the guard holds with the read sending the value, or null when that cannot be decided.
     */
    public static function decide(Expr $guard, ReadPath $read, DiscriminantValue $value, AnalysisScope $scope): ?bool
    {
        try {
            return (bool) new self($read, $value, $scope, resolve(ValueResolver::class))->evaluate($guard);
        } catch (ConstExprEvaluationException) {
            return null;
        }
    }

    /**
     * Whether a value is a scalar, null or an enum case.
     *
     * @phpstan-assert-if-true bool|int|float|string|UnitEnum|null $value
     */
    private static function isPlain(mixed $value): bool
    {
        return $value === null || is_scalar($value) || $value instanceof UnitEnum;
    }

    /**
     * Whether PHP's `==` holds between two values.
     */
    private static function looselyEqual(mixed $left, mixed $right): bool
    {
        // Pint's strict_comparison rule rewrites `==` to `===`; a non-strict in_array() compares exactly as `==` does.
        return in_array($left, [$right], false);
    }

    /**
     * What PHP evaluates an expression as, `&&` and `||` short-circuiting as PHP's own do.
     *
     * @throws ConstExprEvaluationException for a value it cannot know
     */
    private function evaluate(Expr $expr): mixed
    {
        return match (true) {
            $expr instanceof BooleanNot => ! $this->evaluate($expr->expr),
            $expr instanceof BooleanAnd, $expr instanceof LogicalAnd => $this->evaluate($expr->left) && $this->evaluate($expr->right),
            $expr instanceof BooleanOr, $expr instanceof LogicalOr => $this->evaluate($expr->left) || $this->evaluate($expr->right),
            $expr instanceof LogicalXor => $this->evaluate($expr->left) xor $this->evaluate($expr->right),
            $expr instanceof Identical => $this->evaluate($expr->left) === $this->evaluate($expr->right),
            $expr instanceof NotIdentical => $this->evaluate($expr->left) !== $this->evaluate($expr->right),
            $expr instanceof Equal => self::looselyEqual($this->evaluate($expr->left), $this->evaluate($expr->right)),
            $expr instanceof NotEqual => ! self::looselyEqual($this->evaluate($expr->left), $this->evaluate($expr->right)),
            $expr instanceof Isset_ => array_all($expr->vars, fn (Expr $var): bool => $this->evaluate($var) !== null),
            $expr instanceof Empty_ => ! $this->evaluate($expr->expr),
            $expr instanceof FuncCall => $this->call($expr),
            default => $this->operand($expr),
        };
    }

    /**
     * What `is_null()` or `in_array()` returns for the guard's arguments.
     *
     * @throws ConstExprEvaluationException for any other call, or arguments it cannot read
     */
    private function call(FuncCall $call): bool
    {
        $function = $call->name instanceof Name ? $call->name->toLowerString() : '';

        if (! in_array($function, self::FUNCTIONS, true) || $call->isFirstClassCallable()) {
            throw new ConstExprEvaluationException("A call to {$function}() cannot be decided");
        }

        $arguments = CallArguments::for($call, new ReflectionFunction($function));

        if ($arguments->hasUnpack()) {
            throw new ConstExprEvaluationException('A spread argument cannot be decided');
        }

        $argument = fn (string $name): Expr => $arguments->named($name)->value
            ?? throw new ConstExprEvaluationException("No {$name} argument is passed");
        $strict = $arguments->named('strict')?->value;

        return match ($function) {
            'is_null' => $this->evaluate($argument('value')) === null,
            default => in_array(
                $this->evaluate($argument('needle')),
                $this->haystack($argument('haystack')),
                $strict !== null && $this->strict($strict),
            ),
        };
    }

    /**
     * The value one operand holds: the read's value, a member of the case it sends, or a constant.
     *
     * @throws ConstExprEvaluationException for another read, or anything else no constant evaluation knows
     */
    private function operand(Expr $expr): bool|int|float|string|UnitEnum|null
    {
        $path = ReadPath::of($expr);
        $member = $path?->memberOf($this->read);

        return match (true) {
            $path === null => $this->constant($expr),
            $path->same($this->read) => $this->value->value,
            $member === 'value', $member === 'name' => $this->value->member($member)?->value,
            default => throw new ConstExprEvaluationException('Another read cannot be decided'),
        };
    }

    /**
     * A constant operand's value, kept to a scalar, null or an enum case so PHP compares it with no warning.
     *
     * @throws ConstExprEvaluationException for any other value, or an expression that is no constant
     */
    private function constant(Expr $expr): bool|int|float|string|UnitEnum|null
    {
        $value = $this->values->evaluateConstantExpression($expr, $this->scope);

        return self::isPlain($value) ? $value : throw new ConstExprEvaluationException('Only a plain value is compared');
    }

    /**
     * The constant list `in_array()` searches, each item a scalar, null or an enum case.
     *
     * @return array<array-key, mixed>
     *
     * @throws ConstExprEvaluationException for anything else
     */
    private function haystack(Expr $expr): array
    {
        $value = $this->values->evaluateConstantExpression($expr, $this->scope);

        if (! is_array($value) || ! array_all($value, self::isPlain(...))) {
            throw new ConstExprEvaluationException('Only a constant list of plain values is searched');
        }

        return $value;
    }

    /**
     * The constant `strict` flag `in_array()` is passed.
     *
     * @throws ConstExprEvaluationException for anything other than a constant bool
     */
    private function strict(Expr $expr): bool
    {
        $strict = $this->constant($expr);

        return is_bool($strict) ? $strict : throw new ConstExprEvaluationException('Only a constant bool is a strict flag');
    }
}
