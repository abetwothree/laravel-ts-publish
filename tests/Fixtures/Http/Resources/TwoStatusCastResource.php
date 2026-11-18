<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * A test-only resource whose cast builds on a type name two enums the file reads print.
 *
 * @mixin Post
 */
#[TsCasts(['k' => ['type' => 'Record<string, StatusType>', 'import' => '@/types/meta']])]
class TwoStatusCastResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['k' => $this->title, 'a' => $this->status, 'b' => CrmStatus::Lead];
    }
}
