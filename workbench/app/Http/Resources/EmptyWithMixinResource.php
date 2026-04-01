<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;

/**
 * Resource with no toArray override but a known model — tests implicit delegation.
 *
 * @mixin User
 */
class EmptyWithMixinResource extends JsonResource
{
    //
}
