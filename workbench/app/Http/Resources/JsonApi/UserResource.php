<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\JsonApi;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\User;

/**
 * A JSON:API resource naming its attributes only.
 *
 * @mixin User
 */
class UserResource extends JsonApiResource
{
    public $attributes = ['name', 'email'];
}
