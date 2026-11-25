<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

use RuntimeException;

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

    /** @var list<array{done: bool}> rows a loop reads */
    public array $rows = [];

    /** @var array<string, int>|null a number map an emptiness guard reads */
    public ?array $maybeCounts = null;

    /** @var array<string, string>|null a string map `?? []` defaults */
    public ?array $maybeLabels = null;

    /** @var list<int>|null a list `?? []` defaults */
    public ?array $maybeInts = null;

    /** @var array{by: string, k: int} a closed shape given another key */
    public array $meta = ['by' => '', 'k' => 0];

    /** @var array<string, string> a string map whose default is not empty */
    public array $presets = ['tone' => 'warm'];

    public ?string $note = null;

    public int $count = 0;

    public function __construct(
        public MailRecipient $author,
        public string $title = '',
        public bool $flag = false,
        public string $pick = '',
    ) {}

    /**
     * A static open map.
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return ['by' => 'hi', 'tone' => 'warm'];
    }

    /**
     * An open map that writes `by` only when the line is flagged.
     *
     * @return array<string, string>
     */
    public function tones(): array
    {
        return $this->flag ? ['by' => $this->title, 'tone' => 'warm'] : ['tone' => 'warm'];
    }

    /**
     * A number map that writes `by` only when the line is flagged.
     *
     * @return array<string, int>
     */
    public function counted(): array
    {
        return $this->flag ? ['by' => 7, 'n' => 1] : ['n' => 1];
    }

    /**
     * A string map whose body is one literal.
     *
     * @return array<string, string>
     */
    public function labelsLit(): array
    {
        return ['by' => $this->title, 'tone' => 'warm'];
    }

    /**
     * The ints, as a list.
     *
     * @return list<int>
     */
    public function idList(): array
    {
        return $this->ints;
    }

    /**
     * Throws unless the line is flagged.
     */
    public function audit(): void
    {
        if (! $this->flag) {
            throw new RuntimeException('audit');
        }
    }

    /**
     * A closed shape writing `by` as the title.
     *
     * @return array{by: string}
     */
    public function extras(): array
    {
        return ['by' => $this->title];
    }
}
