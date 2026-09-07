<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Declares `who` as a multi-FQCN accessor, so a child redeclaring it collides on the bare name. */
class MergeParentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['who' => $this->last_user_activity_by];
    }
}
