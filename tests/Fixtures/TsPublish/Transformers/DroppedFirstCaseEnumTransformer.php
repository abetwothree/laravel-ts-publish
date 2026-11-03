<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Transformers\EnumTransformer;
use Override;

/** A custom transformer_class whose transformCases() publishes every case but the first. */
final class DroppedFirstCaseEnumTransformer extends EnumTransformer
{
    /**
     * Publish each case after the first.
     */
    #[Override]
    protected function transformCases(): self
    {
        parent::transformCases();

        $this->cases = array_slice($this->cases, 1);

        return $this;
    }
}
