<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/**
 * A child of a resource with a private hooked property, whose own body reads that name through the resource's proxy.
 */
final class PrivateHookChild extends PrivateHookParent {}
