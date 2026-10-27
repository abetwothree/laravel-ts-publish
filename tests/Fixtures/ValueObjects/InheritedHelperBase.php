<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Workbench\App\ValueObjects\PanelRoot;

/**
 * A helper whose `self` and `parent` name this class and PanelRoot, and whose `static` names the subclass calling it.
 */
abstract class InheritedHelperBase extends PanelRoot
{
    public static function label(): int|string
    {
        return 1;
    }

    /** @return array<string, mixed> */
    protected function helper(): array
    {
        return ['self' => self::label(), 'parent' => parent::root(), 'static' => static::label()];
    }
}
