<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/** A vague signature whose body calls itself, so the body fallback must decline instead of looping. */
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
}
