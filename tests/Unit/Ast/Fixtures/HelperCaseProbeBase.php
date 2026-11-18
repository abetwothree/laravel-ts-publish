<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Workbench\App\Enums\Status;

/**
 * Helpers `HelperCaseProbe` reaches through `parent::` and `$this::`, each returning a case its subclass's method of the
 * same name does not.
 */
class HelperCaseProbeBase
{
    /** A case behind a `mixed` declaration. */
    public function published(): mixed
    {
        return Status::Published;
    }

    /**
     * A member read off this class's private helper, which the subclass's public `pick()` does not override.
     *
     * @return array<string, mixed>
     */
    public function baseArray(): array
    {
        return ['private_from_parent' => $this->pick()->value];
    }

    /** A case only this class's body reaches. */
    private function pick(): mixed
    {
        return Status::Draft;
    }
}
