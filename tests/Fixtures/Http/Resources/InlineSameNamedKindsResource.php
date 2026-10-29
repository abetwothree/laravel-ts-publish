<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * A test-only resource whose nested array reads the case names of two same-named enums, and no other token of either.
 *
 * @mixin Post
 */
class InlineSameNamedKindsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'inner' => ['a' => $this->status->name, 'b' => CrmStatus::Lead->name],
        ];
    }
}
