<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload whose `toArray()` reads untyped defaults its subclasses write from closures. */
class CapturedWriteBase
{
    protected $used = 'none';

    protected $anon = 'none';

    protected $loose = 'none';

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['used' => $this->used, 'anon' => $this->anon, 'loose' => $this->loose];
    }
}
