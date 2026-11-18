<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Effects;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use ReflectionClass;
use ReflectionMethod;

/**
 * The method of `$this`, or of a named class, that a call or a callable value runs, read in a body whose `self` is a
 * declaring class and whose `$this` is an instance of the runtime class.
 *
 * @phpstan-type CallableTarget = array{ReflectionClass<object>, string}
 *
 * @internal
 */
final readonly class CallableTargets
{
    /**
     * Targets read for a `$this` that is an instance of $runtime.
     *
     * @param  ReflectionClass<object>  $runtime
     */
    public function __construct(private ReflectionClass $runtime) {}

    /**
     * Whether an expression is `$this`.
     */
    public static function isThis(Node $node): bool
    {
        return $node instanceof Variable && $node->name === 'this';
    }

    /**
     * The class and method a callable value names: a first-class callable of a method run on `$this` or of another
     * class's static method, `[$this, 'm']`, or `'static::m'` and its kin; false where it names one by a name the
     * source does not spell or no class here declares; null where it names no method of this kind.
     *
     * @param  ReflectionClass<object>  $declaring
     * @return CallableTarget|false|null
     */
    public function callableTarget(Expr $callable, ReflectionClass $declaring): array|false|null
    {
        $relative = ReadOnlyCalls::relativeCallable($callable);

        if ($relative !== null) {
            $class = AnalysisScope::relativeClassOf(new Name($relative[0]), $declaring, $this->runtime);

            return $class === null || $relative[1] === null ? false : [$class, $relative[1]];
        }

        if ($callable instanceof Array_) {
            $method = count($callable->items) === 2 && self::isThis($callable->items[0]->value)
                ? $callable->items[1]->value
                : null;

            return match (true) {
                $method instanceof String_ => [
                    AnalysisScope::memberClassOf($declaring, $this->runtime, $method->value, true),
                    $method->value,
                ],
                $method === null => null,
                default => false,
            };
        }

        if (! $callable instanceof CallLike || ! $callable->isFirstClassCallable() || $callable instanceof FuncCall
            || (($callable instanceof MethodCall || $callable instanceof NullsafeMethodCall)
                && ! self::isThis($callable->var))
        ) {
            return null;
        }

        [$class, $method] = $callable instanceof StaticCall && $callable->class instanceof Name
            && $callable->name instanceof Identifier
            ? [$callable->class->toString(), $callable->name->toString()]
            : ['', ''];

        return $this->thisMethod($callable, $declaring)
            ?? (class_exists($class) && method_exists($class, $method)
                ? [new ReflectionClass($class), $method]
                : false);
    }

    /**
     * The class and name of the method a call runs on `$this`: through `$this->`, `$this::`, `self::`, `static::` or
     * `parent::`, or an ancestor's name for a method PHP runs on `$this`; null for any other call.
     *
     * @param  ReflectionClass<object>  $declaring
     * @return CallableTarget|null
     */
    public function thisMethod(CallLike $call, ReflectionClass $declaring): ?array
    {
        if (! ($call instanceof MethodCall || $call instanceof NullsafeMethodCall || $call instanceof StaticCall)
            || ! $call->name instanceof Identifier
        ) {
            return null;
        }

        $method = $call->name->toString();
        $class = match (true) {
            $call instanceof StaticCall => $this->staticCallClass($call, $method, $declaring),
            self::isThis($call->var) => AnalysisScope::memberClassOf($declaring, $this->runtime, $method, true),
            default => null,
        };

        return $class === null ? null : [$class, $method];
    }

    /**
     * The class a static-syntax call runs a method of on `$this`: `$this::` and `static::` the runtime class,
     * `self::` and `parent::` as the declaring class names them, and an ancestor's name for a method it does not
     * declare static.
     *
     * @param  ReflectionClass<object>  $declaring
     * @return ReflectionClass<object>|null
     */
    private function staticCallClass(StaticCall $call, string $method, ReflectionClass $declaring): ?ReflectionClass
    {
        $class = $call->class;

        if (! $class instanceof Name) {
            return self::isThis($class) ? $this->runtime : null;
        }

        if ($class->isSpecialClassName()) {
            return AnalysisScope::relativeClassOf($class, $declaring, $this->runtime);
        }

        $name = $class->toString();

        return class_exists($name) && is_a($this->runtime->getName(), $name, true)
            && (! method_exists($name, $method) || ! new ReflectionMethod($name, $method)->isStatic())
            ? new ReflectionClass($name)
            : null;
    }
}
