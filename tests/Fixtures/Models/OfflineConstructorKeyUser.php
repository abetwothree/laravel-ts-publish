<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

/** An auth model the run cannot inspect whose constructor keys it by string, which only an instance shows. */
class OfflineConstructorKeyUser extends OfflineKeyUser
{
    /** Keys the model by string after Model's own constructor. */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->keyType = 'string';
    }
}
