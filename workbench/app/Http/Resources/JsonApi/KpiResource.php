<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\JsonApi;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Kpi;

/**
 * A JSON:API resource whose morph relationship reaches models with no API resource, so an include sends Laravel's
 * bare JsonApiResource over each, typed by the model's name.
 *
 * @mixin Kpi
 */
class KpiResource extends JsonApiResource
{
    public $attributes = ['value'];

    public $relationships = ['reportable'];
}
