<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Stringable;
use Workbench\App\ValueObjects\OpaqueHandle;

final class ReturnShapeFixture
{
    /**
     * `CustomThing` names no PHP type, standing for a type the consuming app declares as a global.
     *
     * @return array{known: string, handle: OpaqueHandle, maybe?: int, custom?: CustomThing}
     */
    public function shaped(): array
    {
        return [];
    }

    /**
     * `Stringable` is a class, whose import the string-only shape map cannot carry.
     *
     * @return array{text: Stringable, amount: int|float, spare: resource|callable, mode: 'draft'|'live'}
     */
    public function spelled(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function record(): array
    {
        return [];
    }

    public function undocumented(): array // @phpstan-ignore missingType.iterableValue
    {
        return [];
    }
}
