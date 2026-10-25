<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

/**
 * Fixture: the runtime class of BasePanelPayload's `toArray()`, overriding the members that body reads through
 * `$this` and `static`, and redeclaring the parent's private ones, which that body still reads.
 */
final class PanelPayload extends BasePanelPayload
{
    public const LABEL = 7;

    public bool $secret = true;

    protected $tier = 3;

    public static function own(): string
    {
        return 'own';
    }

    public function title(): string
    {
        return 'x';
    }

    public function weight(): string
    {
        return 'heavy';
    }

    /** @return array<string, mixed> */
    protected function extra(): array
    {
        return ['child_only' => true];
    }
}
