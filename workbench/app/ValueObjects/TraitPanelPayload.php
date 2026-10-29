<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

use Workbench\App\ValueObjects\Concerns\ShapesPanel;

/**
 * Fixture: the class that uses ShapesPanel, so that trait's `self::own()` reaches this class's own() and its
 * `parent::root()` reaches PanelRoot's.
 */
final class TraitPanelPayload extends PanelRoot
{
    use ShapesPanel;

    public static function own(): string
    {
        return 'own';
    }
}
