<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Missing\Extension;

/** Untyped defaults naming a class constant and a global constant that this process never loads. */
final class UnloadableDefaultsSubject
{
    public $mode = Extension::MODE;

    public $flag = MISSING_EXTENSION_FLAG;
}
