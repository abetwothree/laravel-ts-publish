<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

/** A concrete subclass of AbstractKeyedModel whose getKey() override sends a value of its own. */
class KeyOverrideKeyedModel extends AbstractKeyedModel
{
    protected $table = 'posts';

    /** Sends a prefixed key. */
    public function getKey(): string
    {
        return 'post-'.$this->getAttribute($this->getKeyName());
    }
}
