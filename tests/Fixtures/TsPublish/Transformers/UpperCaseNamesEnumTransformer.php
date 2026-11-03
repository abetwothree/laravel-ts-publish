<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Transformers\EnumTransformer;
use Override;

/** A custom transformer_class whose transformCases() publishes every case name upper-cased. */
final class UpperCaseNamesEnumTransformer extends EnumTransformer
{
    /**
     * Publish each case under its upper-cased name.
     */
    #[Override]
    protected function transformCases(): self
    {
        parent::transformCases();

        $this->cases = array_map(fn (array $case): array => [...$case, 'name' => strtoupper($case['name'])], $this->cases);

        return $this;
    }
}
