<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\JsonApi;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Category;

/**
 * A JSON:API resource a nullable to-one relationship names.
 *
 * @mixin Category
 */
class CategoryResource extends JsonApiResource
{
    public $attributes = ['name', 'slug'];
}
