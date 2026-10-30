<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload whose seven untyped properties each wrap what the next one's helper returns. */
final class WrappedChainPayload
{
    protected $p0 = 'none';

    protected $p1 = 'none';

    protected $p2 = 'none';

    protected $p3 = 'none';

    protected $p4 = 'none';

    protected $p5 = 'none';

    protected $p6 = 'none';

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'h0' => $this->m0(),
            'h1' => $this->m1(),
            'h2' => $this->m2(),
            'h3' => $this->m3(),
            'h4' => $this->m4(),
            'h5' => $this->m5(),
            'h6' => $this->m6(),
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

    /** Property 4, through a helper that declares no return type. */
    public function m4()
    {
        return $this->p4;
    }

    /** Property 5, through a helper that declares no return type. */
    public function m5()
    {
        return $this->p5;
    }

    /** Property 6, through a helper that declares no return type. */
    public function m6()
    {
        return $this->p6;
    }

    /** Wrap what the next property's helper returns into property 0. */
    public function w0(): static
    {
        $this->p0 = ['v' => $this->m1()];

        return $this;
    }

    /** Wrap what the next property's helper returns into property 1. */
    public function w1(): static
    {
        $this->p1 = ['v' => $this->m2()];

        return $this;
    }

    /** Wrap what the next property's helper returns into property 2. */
    public function w2(): static
    {
        $this->p2 = ['v' => $this->m3()];

        return $this;
    }

    /** Wrap what the next property's helper returns into property 3. */
    public function w3(): static
    {
        $this->p3 = ['v' => $this->m4()];

        return $this;
    }

    /** Wrap what the next property's helper returns into property 4. */
    public function w4(): static
    {
        $this->p4 = ['v' => $this->m5()];

        return $this;
    }

    /** Wrap what the next property's helper returns into property 5. */
    public function w5(): static
    {
        $this->p5 = ['v' => $this->m6()];

        return $this;
    }

    /** Wrap what the next property's helper returns into property 6. */
    public function w6(): static
    {
        $this->p6 = ['v' => $this->m0()];

        return $this;
    }
}
