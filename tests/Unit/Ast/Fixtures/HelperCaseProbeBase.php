<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Workbench\App\Enums\Status;

/** A helper `HelperCaseProbe` reaches through `parent::`, returning a case its override does not. */
class HelperCaseProbeBase
{
    /** A case behind a `mixed` declaration. */
    public function published(): mixed
    {
        return Status::Published;
    }
}
