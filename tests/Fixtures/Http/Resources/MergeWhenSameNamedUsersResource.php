<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AuthoredPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource whose `mergeWhen()` writes one key with the app's `User` or, by default, the CRM's (GH-76).
 *
 * @mixin AuthoredPost
 */
final class MergeWhenSameNamedUsersResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            $this->mergeWhen($request->boolean('crm'), ['party' => $this->crmAuthor], ['party' => $this->author]),
        ];
    }
}
