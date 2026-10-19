<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/** Vague signatures whose bodies call themselves, so the body fallback must decline instead of looping. */
final class RecursiveVagueFixture
{
    /** A bare self-call, so the body spells no properties at all and the vague declaration stands. */
    public function again(): array
    {
        return $this->again();
    }

    /** The same cycle through an array literal, where the recursive call is a value the analyzer resolves. */
    public function againKeyed(): array
    {
        return ['again' => $this->againKeyed()];
    }

    /** Half of a mutual recursion with no declaration, so only the value read follows it. */
    public function a()
    {
        return $this->b();
    }

    /** The other half. */
    public function b()
    {
        return $this->a();
    }
}
