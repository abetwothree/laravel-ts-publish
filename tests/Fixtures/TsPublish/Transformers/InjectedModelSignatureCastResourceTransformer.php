<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Override;

/**
 * A test-only resource transformer that injects a model cast on a backslash signature, spelled with the single
 * backslash a single-quoted paste of the published name leaves, with no #[TsCasts] attribute behind it.
 */
class InjectedModelSignatureCastResourceTransformer extends ResourceTransformer
{
    #[Override]
    protected function parseModelTsCastsOverrides(): self
    {
        parent::parseModelTsCastsOverrides();

        $this->modelTsCastsOverrides['[key: `${string}\unit`]'] = 'number';

        return $this;
    }
}
