<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Override;

/**
 * A test-only resource transformer that injects a cast on a backslash signature in its own parse step, spelled with
 * the single backslash a single-quoted paste of the published name leaves, with no #[TsCasts] attribute behind it.
 */
class InjectedSignatureCastResourceTransformer extends ResourceTransformer
{
    #[Override]
    protected function parseResourceTsCastsOverrides(): self
    {
        parent::parseResourceTsCastsOverrides();

        $this->tsTypeOverrides['[key: `${string}\unit`]'] = 'number';

        return $this;
    }
}
