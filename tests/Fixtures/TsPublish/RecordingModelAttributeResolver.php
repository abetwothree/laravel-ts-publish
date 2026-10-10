<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish;

use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Override;

/**
 * A test-only resolver that overrides buildMorphTargetMap() with its long-standing signature, and records each call.
 */
class RecordingModelAttributeResolver extends ModelAttributeResolver
{
    /** @var list<list<class-string>> */
    public array $morphTargetMapBuilds = [];

    /** @param  list<class-string>  $modelFqcns */
    #[Override]
    public function buildMorphTargetMap(array $modelFqcns): void
    {
        $this->morphTargetMapBuilds[] = $modelFqcns;

        parent::buildMorphTargetMap($modelFqcns);
    }
}
