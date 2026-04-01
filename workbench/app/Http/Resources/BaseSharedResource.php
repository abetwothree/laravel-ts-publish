<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\Concerns\SharedExtendsInterface;

/**
 * Parent resource that uses SharedExtendsInterface — tests BFS dedup when child also uses the same trait.
 */
class BaseSharedResource extends JsonResource
{
    use SharedExtendsInterface;
}
