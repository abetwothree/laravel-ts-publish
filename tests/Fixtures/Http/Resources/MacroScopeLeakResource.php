<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\MacroShadowedPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A caller local named like a variable a macro closure captures, and a macro that calls itself.
 *
 * @mixin MacroShadowedPost
 */
class MacroScopeLeakResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $suffix = 3;

        return [
            'excl' => $this->excl(),
            'arrow_excl' => $this->arrowExcl(),
            'again' => $this->again(),
            'with_arg' => $this->echoArg(5),
        ];
    }
}
