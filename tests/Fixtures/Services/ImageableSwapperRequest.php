<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services;

/**
 * A request stand-in that is no `Illuminate\Http\Request`, whose `user()` hands back an ImageableSwapper.
 */
final class ImageableSwapperRequest
{
    /**
     * The swapper standing in for the signed-in user.
     */
    public function user(): ImageableSwapper
    {
        return new ImageableSwapper;
    }
}
