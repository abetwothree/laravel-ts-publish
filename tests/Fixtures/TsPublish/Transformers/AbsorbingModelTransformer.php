<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;

/**
 * A test-only model transformer that hands out its redundant-arm absorbing, so a test can absorb a type info it builds.
 *
 * @phpstan-import-type TypeScriptTypeInfo from \AbeTwoThree\LaravelTsPublish\LaravelTsPublish
 */
class AbsorbingModelTransformer extends ModelTransformer
{
    /**
     * The info as the model's absorbing leaves it.
     *
     * @param  TypeScriptTypeInfo  $info
     * @return TypeScriptTypeInfo
     */
    public function absorbed(array $info): array
    {
        return $this->withRedundantArmsAbsorbed($info);
    }
}
