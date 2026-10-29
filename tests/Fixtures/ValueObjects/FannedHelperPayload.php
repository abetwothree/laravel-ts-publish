<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload whose four untyped properties each write a list of what every helper returns. */
final class FannedHelperPayload
{
    protected $p0 = 'none';

    protected $p1 = 'none';

    protected $p2 = 'none';

    protected $p3 = 'none';

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'h0' => $this->m0(),
            'h1' => $this->m1(),
            'h2' => $this->m2(),
            'h3' => $this->m3(),
        ];
    }

    /** Property 0, through a helper that declares no return type. */
    public function m0()
    {
        return $this->p0;
    }

    /** Property 1, through a helper that declares no return type. */
    public function m1()
    {
        return $this->p1;
    }

    /** Property 2, through a helper that declares no return type. */
    public function m2()
    {
        return $this->p2;
    }

    /** Property 3, through a helper that declares no return type. */
    public function m3()
    {
        return $this->p3;
    }

    /** Write property 0 a list of what every helper returns. */
    public function w0(): static
    {
        $this->p0 = [$this->m0(), $this->m1(), $this->m2(), $this->m3()];

        return $this;
    }

    /** Write property 1 a list of what every helper returns. */
    public function w1(): static
    {
        $this->p1 = [$this->m0(), $this->m1(), $this->m2(), $this->m3()];

        return $this;
    }

    /** Write property 2 a list of what every helper returns. */
    public function w2(): static
    {
        $this->p2 = [$this->m0(), $this->m1(), $this->m2(), $this->m3()];

        return $this;
    }

    /** Write property 3 a list of what every helper returns. */
    public function w3(): static
    {
        $this->p3 = [$this->m0(), $this->m1(), $this->m2(), $this->m3()];

        return $this;
    }
}
