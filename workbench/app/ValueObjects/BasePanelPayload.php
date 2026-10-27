<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

/**
 * Fixture: an inherited `toArray()` whose `$this`, `static`, `self` and `parent` reads each name a different class
 * once PanelPayload runs it, beside a private property and method the subclass redeclares.
 */
abstract class BasePanelPayload extends PanelRoot
{
    public const LABEL = 'base';

    protected $tier = 'gold';

    private string $secret = 'x';

    public static function own(): int|string
    {
        return 0;
    }

    public function title(): int|string
    {
        return 0;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'title' => $this->title(),
            ...$this->extra(),
            'label' => static::LABEL,
            'tier' => $this->tier,
            'secret' => $this->secret,
            'weight' => $this->weight(),
            'own' => self::own(),
            'root' => parent::root(),
            'late' => static::own(),
        ];
    }

    /** @return array<string, mixed> */
    protected function extra(): array
    {
        return ['base_only' => 1];
    }

    private function weight(): int
    {
        return 1;
    }
}
