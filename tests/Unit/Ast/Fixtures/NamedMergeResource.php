<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Hands every merge helper its array by name, with the arguments written out of declared order. */
class NamedMergeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            $this->merge(value: ['title' => $this->title]),
            $this->mergeWhen(value: ['published' => $this->published_at], condition: $this->id > 0),
            $this->mergeUnless(value: ['content' => $this->content], condition: $this->id > 0),
        ];
    }
}
