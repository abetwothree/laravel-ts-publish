<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use Override;

/**
 * A test-only model transformer whose enum property info leaves out `alsoBare`, as an override written before that
 * info carried it builds it.
 */
class PreBareFlagModelTransformer extends ModelTransformer
{
    #[Override]
    protected function buildEnumColumns(): array
    {
        return $this->withoutBareFlag(parent::buildEnumColumns());
    }

    #[Override]
    protected function buildEnumMutators(): array
    {
        return $this->withoutBareFlag(parent::buildEnumMutators());
    }

    #[Override]
    protected function buildEnumAppends(): array
    {
        return $this->withoutBareFlag(parent::buildEnumAppends());
    }

    /**
     * Each property's info without its `alsoBare` key.
     *
     * @param  array<string, array<string, mixed>>  $properties
     * @return array<string, array<string, mixed>>
     */
    private function withoutBareFlag(array $properties): array
    {
        return array_map(fn (array $info): array => array_diff_key($info, ['alsoBare' => true]), $properties);
    }
}
