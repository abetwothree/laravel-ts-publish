<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects\Spreads;

use Workbench\App\ValueObjects\MailRecipient;

/**
 * An author, a title and a sheet whose map a subclass may override, beside methods that return a typed map written
 * with one more key (GH-161 C2).
 */
final class SeedShelf
{
    /** @var array<string, string> a string map */
    public array $labels = [];

    /** @var array<string, string>|null a string map, or null */
    public ?array $maybeLabels = null;

    public function __construct(
        public MailRecipient $author,
        public ToneSheet $sheet,
        public SeedInner $inner = new SeedInner,
        public string $title = '',
    ) {}

    /**
     * The inner object's map, written with one more key.
     */
    public function fromOtherCall(): array
    {
        $d = $this->inner->labels();
        $d['seen'] = true;

        return $d;
    }

    /**
     * The own property map, written with one more key.
     */
    public function fromProperty(): array
    {
        $d = $this->labels;
        $d['seen'] = true;

        return $d;
    }

    /**
     * The nullable map or `[]`, written with one more key.
     */
    public function fromCoalesced(): array
    {
        $d = $this->maybeLabels ?? [];
        $d['seen'] = true;

        return $d;
    }

    /**
     * A static call's map, written with one more key.
     */
    public function fromStaticCall(): array
    {
        $d = SeedInner::presets();
        $d['seen'] = true;

        return $d;
    }

    /**
     * The inner object's map written with one more key, nested as a value.
     */
    public function nestedOther(): array
    {
        $d = $this->inner->labels();
        $d['seen'] = true;

        return ['row' => $d];
    }
}
