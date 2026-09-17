<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/** A plain subject, so every constant key survives: `parent::`, `self::`, and a foreign class's. */
final class ConstantKeyFixture extends ConstantKeyParent
{
    public const string LABEL_KEY = 'label';

    /** Three constant-key forms in one literal body, including a string-valued constant. */
    public function shape(): array
    {
        return [
            parent::PARENT_TIER => 'parent-const',
            self::LABEL_KEY => 'string-valued',
            ConstantKeyResource::EXTERNAL_KEY => 'foreign',
        ];
    }
}
