<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\WrapAccessorGrade;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Declares no toArray(), so it sends its model's own array, appended wraps among it (GH-160).
 *
 * @mixin WrapAccessorGrade
 */
class WrapAccessorDelegatedResource extends JsonResource {}
