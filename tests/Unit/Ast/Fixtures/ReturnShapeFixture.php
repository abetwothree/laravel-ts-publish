<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Workbench\App\ValueObjects\OpaqueHandle;

final class ReturnShapeFixture
{
    /**
     * @return array{known: string, handle: OpaqueHandle, maybe?: int}
     */
    public function shaped(): array
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
