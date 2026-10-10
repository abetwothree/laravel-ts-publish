<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastEventTransformer;
use Override;

/**
 * A test-only event transformer that injects a cast on a backslash signature in its own parse step, spelled with the
 * single backslash a single-quoted paste of the published name leaves, with no #[TsCasts] attribute behind it.
 */
class InjectedSignatureCastEventTransformer extends BroadcastEventTransformer
{
    #[Override]
    protected function parseTsCasts(): self
    {
        parent::parseTsCasts();

        $this->tsTypeOverrides['[key: `${string}\_cast`]'] = 'boolean';

        return $this;
    }
}
