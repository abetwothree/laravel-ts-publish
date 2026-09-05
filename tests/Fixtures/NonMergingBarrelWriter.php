<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Writers\BarrelWriter;
use Illuminate\Support\Collection;

class NonMergingBarrelWriter extends BarrelWriter
{
    /**
     * Write customized modular barrel files.
     *
     * @param  Collection<int, mixed>  $generators
     * @return array<string, string>
     */
    public function writeModular(Collection $generators, ?string $outputBase = null): array
    {
        return parent::writeModular($generators, $outputBase);
    }
}
