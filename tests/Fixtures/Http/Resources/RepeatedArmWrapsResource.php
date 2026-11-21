<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * A test-only resource whose literals repeat an app enum's wrap in two arms beside a CRM enum's in a third (GH-160).
 *
 * @mixin Post
 */
final class RepeatedArmWrapsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'repeated' => $request->boolean('a')
                ? ['e' => EnumResource::make($this->status), 'x' => 1]
                : ($request->boolean('b')
                    ? ['e' => EnumResource::make($this->status), 'x' => 1]
                    : ['e' => EnumResource::make(CrmStatus::Lead), 'x' => 'a']),
        ];
    }
}
