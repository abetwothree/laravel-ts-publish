<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Attributes;

use Attribute;

/**
 * This attribute can be applied to a resource class to configure TypeScript generation.
 *
 * Use the `name` parameter to override the generated interface name.
 * Use the `model` parameter to specify the backing model class when it cannot be inferred from @mixin.
 * Use the `discriminated` parameter to turn this resource's discriminated union form on or off, whatever the
 * `ts-publish.resources.discriminated_unions` config says.
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
        public ?bool $discriminated = null,
    ) {}
}
