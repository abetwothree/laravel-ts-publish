<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Attributes;

use Attribute;

/**
 * This attribute can be applied to a resource class to configure TypeScript generation.
 *
 * Use the `name` parameter to override the generated interface name.
 * Use the `model` parameter to specify the backing model class when it cannot be inferred from @mixin.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class TsResource
{
    /**
     * @param  class-string|null  $model
     */
    public function __construct(
        public ?string $name = null,
        public ?string $model = null,
        public string $description = '',
    ) {}
}
