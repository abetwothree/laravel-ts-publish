<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\MacroShadowedPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A chain through a macro that shadows a model method; at runtime the macro's Stringable has no format().
 *
 * @mixin MacroShadowedPost
 */
class MacroShadowingChainResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'moment_year' => $this->moment()->format('Y'),
        ];
    }
}
