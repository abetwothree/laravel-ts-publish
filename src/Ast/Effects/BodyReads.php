<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Effects;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodLocator;
use Closure;
use PhpParser\Node\Stmt;
use ReflectionClass;

/**
 * The method bodies one write scan reads: each at most once per question, and no deeper than DEPTH levels below the
 * window's items. A body any deeper, or one the locator cannot read, may change the read.
 *
 * A body is judged once a publish run for each scan alike: the bodies it asks about, in order, and what it finds
 * itself with each of them answered no. A scan replays that where it reaches the body, which answers as reading the
 * body there would, since a body that may change the read ends the whole scan and no caller drops that answer.
 *
 * @phpstan-type BodyAsk = array{ValueUse|null, ReflectionClass<object>, string}
 * @phpstan-type BodyJudgement = array{asks: list<BodyAsk>, changes: bool}
 *
 * @internal
 */
final class BodyReads
{
    /** How many levels of `$this` method bodies one scan reads. */
    public const int DEPTH = 3;

    /** @var array<string, true> each body read, or being read, by question, class and lowercased name */
    private array $read = [];

    /** @var array<string, BodyAsk>|null the bodies the body being judged asks about, by key; null while none is */
    private ?array $asked = null;

    /**
     * The reads of one scan.
     *
     * @param  string  $scan  what the judges' answers hang on besides the body, which keys them for the run
     * @param  Closure(array<Stmt>, ReflectionClass<object>): bool  $changes  whether a body may change the read, as
     *                                                                        the class `self` names in it reads it
     * @param  Closure(array<Stmt>, ReflectionClass<object>, ValueUse): bool  $returns  whether what a body returns may
     *                                                                                  run code that changes the read
     */
    public function __construct(
        private readonly MethodLocator $locator,
        private readonly string $scan,
        private readonly Closure $changes,
        private readonly Closure $returns,
    ) {}

    /**
     * Whether running a method on `$this` may change the read through its body.
     *
     * @param  ReflectionClass<object>  $class
     */
    public function mayWrite(ReflectionClass $class, string $method): bool
    {
        return $this->ask(null, $class, $method, 0);
    }

    /**
     * Whether what a method run on `$this` returns may run code that changes the read, used as $use.
     *
     * @param  ReflectionClass<object>  $class
     */
    public function returnsMayRun(ReflectionClass $class, string $method, ValueUse $use): bool
    {
        return $this->ask($use, $class, $method, 0);
    }

    /**
     * Whether asking a question of a method's body, $depth levels down, may find a change: none where it is read
     * already, or being read, and none asked from inside a body being judged, which only notes the ask.
     *
     * @param  ValueUse|null  $use  how what the body returns is used, or null to ask of the body itself
     * @param  ReflectionClass<object>  $class
     */
    private function ask(?ValueUse $use, ReflectionClass $class, string $method, int $depth): bool
    {
        $key = ($use === null ? 'body' : 'returns:'.$use->name).':'.$class->getName().'::'.strtolower($method);

        if ($this->asked !== null) {
            $this->asked[$key] ??= [$use, $class, $method];

            return false;
        }

        if (isset($this->read[$key])) {
            return false;
        }

        $judged = $depth < self::DEPTH ? $this->judgement($key, $use, $class, $method) : null;

        if ($judged === null) {
            return true;
        }

        $this->read[$key] = true;

        return array_any(
            $judged['asks'],
            fn (array $asked): bool => $this->ask($asked[0], $asked[1], $asked[2], $depth + 1),
        ) || $judged['changes'];
    }

    /**
     * What a body asks and finds, read once a run; null where the locator cannot read it.
     *
     * @param  ReflectionClass<object>  $class
     * @return BodyJudgement|null
     */
    private function judgement(string $key, ?ValueUse $use, ReflectionClass $class, string $method): ?array
    {
        return resolve(AnalysisMemo::class)->rememberFact(
            'body-reads:'.$this->scan.':'.$key,
            fn (): ?array => $this->judge($use, $class, $method),
        );
    }

    /**
     * What a body asks and finds, judged with every body it asks about answered no; null where the locator cannot
     * read it.
     *
     * @param  ReflectionClass<object>  $class
     * @return BodyJudgement|null
     */
    private function judge(?ValueUse $use, ReflectionClass $class, string $method): ?array
    {
        $body = $this->locator->locate($class->getName(), $method)?->method->stmts;

        if ($body === null) {
            return null;
        }

        $declaring = $class->getMethod($method)->getDeclaringClass();
        $this->asked = [];

        try {
            $changes = $use === null
                ? ($this->changes)($body, $declaring)
                : ($this->returns)($body, $declaring, $use);
            $asks = $this->asked ?? [];
        } finally {
            $this->asked = null;
        }

        return ['asks' => array_values($asks), 'changes' => $changes];
    }
}
