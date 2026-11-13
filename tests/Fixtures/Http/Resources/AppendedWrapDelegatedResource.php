<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AppendedPriorityWrapPost;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Declares no toArray(), so JsonResource's sends its post's own array, which filter() walks.
 *
 * @mixin AppendedPriorityWrapPost
 */
class AppendedWrapDelegatedResource extends JsonResource {}
