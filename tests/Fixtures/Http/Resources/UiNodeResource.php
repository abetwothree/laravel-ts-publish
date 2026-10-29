<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An API resource with an override, so a `new` of it publishes as a resource does, not as its override.
 */
#[TsType(['type' => 'UiNode', 'import' => '@js/types/ui'])]
class UiNodeResource extends JsonResource
{
    /**
     * The node's kind.
     *
     * @return array{kind: string}
     */
    public function toArray(Request $request): array
    {
        return ['kind' => 'node'];
    }
}
