<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;

/**
 * A class whose override is spelled like the workbench `User` model, so a union of the two spells one name twice.
 */
#[TsType(['type' => 'User', 'import' => '@js/types/ui'])]
final class Person
{
    public string $name = 'Ada';
}
