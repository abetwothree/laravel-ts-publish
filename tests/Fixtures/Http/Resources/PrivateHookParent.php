<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A resource declaring a private property with a get hook, which only its own body reaches.
 */
class PrivateHookParent extends JsonResource
{
    private bool $flag {
        get => (bool) random_int(0, 1);
    }
}
