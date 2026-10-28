<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

/**
 * A model with no override of its own, so it publishes its parent model's.
 */
class InheritingChildModel extends OverriddenBaseModel {}
