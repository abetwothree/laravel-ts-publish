<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use Closure;

/**
 * Hands one arm's class FQCNs out to the tokens that spell them, an occurrence at a time, left to right.
 *
 * The arm's FQCN list is the queue TsTypeString::aliasPropertyType() walks: one entry per occurrence of a name, the
 * last one covering any further occurrence. Both read their tokens through TsTypeString::queuedTokenPattern() and
 * queuePosition(), so a token cannot count for one walk and not for the other.
 *
 * @phpstan-type QueuedClasses = array{classFqcns: list<class-string>, classTokenFqcns?: list<class-string>}
 *
 * @internal
 */
final class ClassTokenQueue
{
    /** @var array<string, non-empty-list<class-string>> name => the FQCNs it spells, in occurrence order */
    private array $queues = [];

    /** @var array<string, int> name => how many of its occurrences have been read */
    private array $seen = [];

    /** @var array<string, string> each name a token is spelled with => the name its queue is kept under */
    private array $queueOf = [];

    private ?string $pattern = null;

    /**
     * Queue each class under its name. A token spelling one of its sibling names, as an enum's `…Kind` is beside its
     * `…Type`, reads the same queue, so two same-named enums' tokens of either name keep one order.
     *
     * @param  list<class-string>  $fqcns  one per token, in the order the arm's type spells them
     * @param  Closure(class-string): string  $nameOf  the name a class's token is spelled with
     * @param  (Closure(class-string): list<string>)|null  $alsoSpelledAs  the class's sibling names
     */
    public function __construct(array $fqcns, Closure $nameOf, ?Closure $alsoSpelledAs = null)
    {
        foreach ($fqcns as $fqcn) {
            $name = $nameOf($fqcn);
            $this->queues[$name][] = $fqcn;
            $this->queueOf[$name] = $name;

            foreach ($alsoSpelledAs === null ? [] : $alsoSpelledAs($fqcn) as $sibling) {
                $this->queueOf[$sibling] ??= $name;
            }
        }

        if ($this->queueOf !== []) {
            $this->pattern = TsTypeString::queuedTokenPattern(array_keys($this->queueOf));
        }
    }

    /**
     * The classes aliasing walks against an info's tokens: its per-token queue when it has one, else each class once.
     *
     * @param  QueuedClasses  $info
     * @return list<class-string>
     */
    public static function fqcnsOf(array $info): array
    {
        return $info['classTokenFqcns'] ?? $info['classFqcns'];
    }

    /**
     * A queue with one entry per token of its type for each name that has a single class behind it.
     *
     * A merge by text can queue that class more or less often than the type spells it, and the next queue joined on
     * would read the difference. A name with two classes, or one to leave, is left as it came.
     *
     * @param  list<class-string>  $queue
     * @param  Closure(class-string): string  $nameOf  the name a class's token is spelled with
     * @param  list<string>  $leave  names to leave as they came: a queue joined to this one gives them a class too
     * @return list<class-string>
     */
    public static function perToken(array $queue, string $type, Closure $nameOf, array $leave = []): array
    {
        /** @var array<string, list<class-string>> $classesOf name => the classes queued for it, in order */
        $classesOf = [];

        foreach ($queue as $fqcn) {
            $classesOf[$nameOf($fqcn)][] = $fqcn;
        }

        /** @var array<string, int> $room name => the entries it still gets, for a name that has one class behind it */
        $room = [];

        foreach ($classesOf as $name => $classes) {
            if (count(array_unique($classes)) === 1 && ! in_array($name, $leave, true)) {
                // At least one: this rule leaves an entry for a class the type does not spell as it came.
                $room[$name] = max(1, (int) preg_match_all(TsTypeString::queuedTokenPattern([$name]), $type));
            }
        }

        $perToken = [];

        foreach ($queue as $fqcn) {
            $name = $nameOf($fqcn);

            if (! isset($room[$name])) {
                $perToken[] = $fqcn;
            } elseif ($room[$name] > 0) {
                $perToken[] = $fqcn;
                $room[$name]--;
            }
        }

        foreach ($room as $name => $left) {
            for ($i = 0; $i < $left; $i++) {
                $perToken[] = $classesOf[$name][0];
            }
        }

        return $perToken;
    }

    /**
     * The class behind each of this arm's tokens in the text, in order. Call once per piece of the arm's type, in
     * the order the type spells them.
     *
     * @return list<class-string>
     */
    public function take(string $text): array
    {
        return array_column($this->spellings($text), 0);
    }

    /**
     * The class behind each of this arm's tokens in the text, in order, beside the name the token spells. Reads the
     * queue as take() does.
     *
     * @return list<array{class-string, string}>
     */
    public function spellings(string $text): array
    {
        if ($this->pattern === null || preg_match_all($this->pattern, $text, $matches) === 0) {
            return [];
        }

        return array_map(fn (string $spelled): array => [$this->classFor($spelled), $spelled], $matches[0]);
    }

    /**
     * The text with each of this arm's tokens respelled by `$spell`, given the class behind it, the name it spells and
     * the character right after it. Reads the queue as take() does.
     *
     * @param  Closure(class-string, string, string): string  $spell
     */
    public function rewrite(string $text, Closure $spell): string
    {
        if ($this->pattern === null || preg_match_all($this->pattern, $text, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return $text;
        }

        $rewritten = '';
        $from = 0;

        foreach ($matches[0] as [$spelled, $at]) {
            $end = $at + strlen($spelled);
            $rewritten .= substr($text, $from, $at - $from).$spell($this->classFor($spelled), $spelled, substr($text, $end, 1));
            $from = $end;
        }

        return $rewritten.substr($text, $from);
    }

    /**
     * Whether a name the tokens read is queued for two classes, and more often than they read it: a class then sits
     * behind another's token, and the queue cannot say which token is whose. Ask once take() has read the whole arm.
     */
    public function outrunsItsTokens(): bool
    {
        return array_any(
            $this->seen,
            fn (int $read, string $name): bool => $read < count($this->queues[$name])
                && count(array_unique($this->queues[$name])) > 1,
        );
    }

    /**
     * The class behind the next token that spells the name, counting it as read.
     *
     * @return class-string
     */
    private function classFor(string $spelled): string
    {
        $name = $this->queueOf[$spelled];
        $occurrence = $this->seen[$name] ?? 0;
        $this->seen[$name] = $occurrence + 1;

        return $this->queues[$name][TsTypeString::queuePosition($occurrence, count($this->queues[$name]))];
    }
}
