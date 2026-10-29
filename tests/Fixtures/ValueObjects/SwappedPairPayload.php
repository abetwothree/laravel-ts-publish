<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload whose two untyped properties each write what the other's helper returns. */
final class SwappedPairPayload
{
    protected $p = 'a';

    protected $q = 1;

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['mp' => $this->mp(), 'mq' => $this->mq(), 'p' => $this->p, 'q' => $this->q];
    }

    /** The first property, through a helper that declares no return type. */
    public function mp()
    {
        return $this->p;
    }

    /** The second property, through a helper that declares no return type. */
    public function mq()
    {
        return $this->q;
    }

    /** Write the first property what the second's helper returns. */
    public function swapP(): static
    {
        $this->p = $this->mq();

        return $this;
    }

    /** Write the second property what the first's helper returns. */
    public function swapQ(): static
    {
        $this->q = $this->mp();

        return $this;
    }
}
