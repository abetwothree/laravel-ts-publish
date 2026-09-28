<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Support\TsTypeString;

/** A TsTypeString that counts each type string it actually qualifies, so a test can see the memo answer instead. */
class CountingTsTypeString extends TsTypeString
{
    /** How many type strings have been qualified rather than read back. */
    public int $qualifications = 0;

    /**
     * Count the qualification, then run it.
     *
     * @param  array<string, list<string>>  $namespacedTypes
     * @param  array<string, string>  $aliasResolution
     */
    protected function qualifyGlobalTypeOnce(string $typeStr, array $namespacedTypes, string $skipNamespace, array $aliasResolution): string
    {
        $this->qualifications++;

        return parent::qualifyGlobalTypeOnce($typeStr, $namespacedTypes, $skipNamespace, $aliasResolution);
    }
}
