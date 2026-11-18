<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Effects;

use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodLocator;
use Closure;
use PhpParser\Node\Stmt;
use ReflectionClass;

/**
 * The method bodies one write scan reads: each at most once per question, and no deeper than DEPTH levels below the
 * window's items. A body any deeper, or one the locator cannot read, may change the read.
 *
 * @internal
 */
final class BodyReads
{
    /** How many levels of `$this` method bodies one scan reads. */
    public const int DEPTH = 3;

    /** @var array<string, true> each body read, or being read, by question, class and lowercased name */
    private array $read = [];

    /**
     * The reads of one scan.
     */
    public function __construct(private readonly MethodLocator $locator) {}

    /**
     * Whether asking a question of a method's body, $depth levels down, may find a change. A body read already, or
     * being read, counts where it is read, since a body that may change the read ends the whole scan: no caller may
     * drop that answer. Otherwise $judge answers for its statements, the class `self` names in them, and their depth.
     *
     * @param  ReflectionClass<object>  $class
     * @param  Closure(array<Stmt>, ReflectionClass<object>, int): bool  $judge
     */
    public function mayWrite(string $question, ReflectionClass $class, string $method, int $depth, Closure $judge): bool
    {
        $key = $question.':'.$class->getName().'::'.strtolower($method);

        if (isset($this->read[$key])) {
            return false;
        }

        $body = $depth < self::DEPTH ? $this->locator->locate($class->getName(), $method)?->method->stmts : null;

        if ($body === null) {
            return true;
        }

        $this->read[$key] = true;

        return $judge($body, $class->getMethod($method)->getDeclaringClass(), $depth + 1);
    }
}
