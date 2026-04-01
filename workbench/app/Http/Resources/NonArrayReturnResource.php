<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;

/** @mixin User */
class NonArrayReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_merge(['id' => $this->id], ['name' => $this->name]);
    }
}
