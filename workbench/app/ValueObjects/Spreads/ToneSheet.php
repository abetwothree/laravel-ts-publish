<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects\Spreads;

/**
 * CreditLine's `tones()` on a class that is not final, so a subclass may override it (GH-161 C2, Q-A).
 */
class ToneSheet
{
    public function __construct(
        public string $title = '',
        public bool $flag = false,
    ) {}

    /**
     * An open map that writes `by` only when the sheet is flagged.
     *
     * @return array<string, string>
     */
    public function tones(): array
    {
        return $this->flag ? ['by' => $this->title, 'tone' => 'warm'] : ['tone' => 'warm'];
    }
}
