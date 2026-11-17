<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;

/**
 * A test-only model transformer that hands out its redundant-arm fold, so a test can fold a type info it builds.
 *
 * @phpstan-import-type TypeScriptTypeInfo from \AbeTwoThree\LaravelTsPublish\LaravelTsPublish
 */
class FoldingModelTransformer extends ModelTransformer
{
    /**
     * The info as the model fold leaves it.
     *
     * @param  TypeScriptTypeInfo  $info
     * @return TypeScriptTypeInfo
     */
    public function folded(array $info): array
    {
        return $this->withRedundantArmsFolded($info);
    }
}
