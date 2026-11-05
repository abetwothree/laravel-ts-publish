<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\SpacedDocblockResource;
use Workbench\App\Models\Post;

/**
 * Builds API resources around its post whose own declarations decide a read: an overridden magic method, a declared
 * property, a resource held, and `$resource` read whole.
 *
 * @extends JsonResource<Post>
 */
class ForwardRulesPostResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'get_title' => MagicGetItemResource::make($this->resource)->title,
            'get_key' => MagicGetItemResource::make($this->resource)->getKey(),
            'call_title' => MagicCallItemResource::make($this->resource)->title,
            'call_key' => MagicCallItemResource::make($this->resource)->getKey(),
            'own_title' => OwnTitleItemResource::make($this->resource)->title,
            'self_title' => SelfHoldingResource::make($this->resource)->title,
            'whole' => PageItemResource::make($this->resource)->resource,
            'nullable_whole' => NullablePostExtendsResource::make($this->resource)->resource,
            'redeclared_whole' => SpacedDocblockResource::make($this->resource)->resource,
        ];
    }
}
