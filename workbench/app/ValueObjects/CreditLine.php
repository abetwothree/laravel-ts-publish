<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

/**
 * An author, a title, a flag and a pick the value-spread probes read (GH-154, GH-161).
 */
final class CreditLine
{
    /** @var array<string, string> a string map a spread writes */
    public array $labels = [];

    /** @var array<string, int> a number map a spread writes after it */
    public array $counts = [];

    /** @var list<int> a list a spread appends after a map */
    public array $ints = [];

    public function __construct(
        public MailRecipient $author,
        public string $title = '',
        public bool $flag = false,
        public string $pick = '',
    ) {}

    /**
     * An open map that writes `by` only when the line is flagged.
     *
     * @return array<string, string>
     */
    public function tones(): array
    {
        return $this->flag ? ['by' => $this->title, 'tone' => 'warm'] : ['tone' => 'warm'];
    }
}
