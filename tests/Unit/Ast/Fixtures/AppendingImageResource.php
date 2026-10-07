<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Declares no toArray() over a model that appends an accessor with no annotation.
 *
 * @mixin AppendingImage
 */
final class AppendingImageResource extends JsonResource {}
