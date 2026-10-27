<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload whose `toArray()` reads untyped defaults its subclass writes from inside callbacks. */
class EnclosedWriteBase
{
    protected $last = 'none';

    protected $arrowed = 'none';

    protected $local = 'none';

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['last' => $this->last, 'arrowed' => $this->arrowed, 'local' => $this->local];
    }
}
