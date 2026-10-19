<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\JsonApi;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Post;

/**
 * A JSON:API resource naming no attributes, so toAttributes() falls back to the model's own toArray().
 *
 * @mixin Post
 */
class BarePostResource extends JsonApiResource {}
