<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use Closure;

/**
 * Hands one arm's class FQCNs out to the tokens that spell them, an occurrence at a time, left to right.
 *
 * The arm's FQCN list is the queue TsTypeString::aliasPropertyType() walks: one entry per occurrence of a name, the
 * last one covering any further occurrence. Both read their tokens through TsTypeString::queuedTokenPattern() and
 * queuePosition(), so a token cannot count for one walk and not for the other.
 *
 * @internal
 */
final class ClassTokenQueue
{
    /** @var array<string, non-empty-list<class-string>> name => the FQCNs it spells, in occurrence order */
    private array $queues = [];

    /** @var array<string, int> name => how many of its occurrences have been read */
    private array $seen = [];

    private ?string $pattern = null;

    /**
     * @param  list<class-string>  $fqcns  one per token, in the order the arm's type spells them
     * @param  Closure(class-string): string  $nameOf  the name a class's token is spelled with
     */
    public function __construct(array $fqcns, Closure $nameOf)
    {
        foreach ($fqcns as $fqcn) {
            $this->queues[$nameOf($fqcn)][] = $fqcn;
        }

        if ($this->queues !== []) {
            $this->pattern = TsTypeString::queuedTokenPattern(array_keys($this->queues));
        }
    }

    /**
     * The class behind each of this arm's tokens in the text, in order. Call once per piece of the arm's type, in
     * the order the type spells them.
     *
     * @return list<class-string>
     */
    public function take(string $text): array
    {
        if ($this->pattern === null || preg_match_all($this->pattern, $text, $matches) === 0) {
            return [];
        }

        $taken = [];

        foreach ($matches[0] as $name) {
            $occurrence = $this->seen[$name] ?? 0;
            $this->seen[$name] = $occurrence + 1;
            $taken[] = $this->queues[$name][TsTypeString::queuePosition($occurrence, count($this->queues[$name]))];
        }

        return $taken;
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
}
