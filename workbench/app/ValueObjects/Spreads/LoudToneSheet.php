<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects\Spreads;

/**
 * A subclass whose `tones()` writes `z` and drops `tone`, which PHP runs wherever the code holds a ToneSheet (GH-161).
 */
final class LoudToneSheet extends ToneSheet
{
    /**
     * A map that writes `z` alone.
     *
     * @return array<string, string>
     */
    public function tones(): array
    {
        return ['z' => 'loud'];
    }
}
