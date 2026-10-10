<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Override;

/**
 * A test-only resource transformer that injects a cast in its own parse step, with no #[TsCasts] attribute behind it.
 */
class InjectedCastResourceTransformer extends ResourceTransformer
{
    #[Override]
    protected function parseResourceTsCastsOverrides(): self
    {
        parent::parseResourceTsCastsOverrides();

        $this->tsTypeOverrides['injected'] = 'string';

        return $this;
    }
}
