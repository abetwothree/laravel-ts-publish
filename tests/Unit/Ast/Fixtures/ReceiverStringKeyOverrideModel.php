<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Model;

/** A concrete model keeping the default `int` key type while its getKey() override returns a string. */
final class ReceiverStringKeyOverrideModel extends Model
{
    protected $table = 'posts';

    /** Declares the key a string, overriding the inherited `mixed`. */
    public function getKey(): string
    {
        return (string) parent::getKey();
    }
}
