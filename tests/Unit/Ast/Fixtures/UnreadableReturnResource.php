<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A returned function call whose keys cannot be read statically: the analysis must stay empty. */
class UnreadableReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_filter(['id' => $this->id]);
    }
}
