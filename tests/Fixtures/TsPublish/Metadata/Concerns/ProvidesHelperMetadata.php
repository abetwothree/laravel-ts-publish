<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata\Concerns;

use Illuminate\Database\Eloquent\Model;

/** A test-only trait whose provide() reaches the helpers of the class using it, through `$this->` and `self::`. */
trait ProvidesHelperMetadata
{
    /**
     * Provide what the using class's helpers return.
     *
     * @return array<string, mixed>
     */
    public function provide(Model $model): array
    {
        return ['helper' => $this->helper(), 'own' => self::own()];
    }
}
