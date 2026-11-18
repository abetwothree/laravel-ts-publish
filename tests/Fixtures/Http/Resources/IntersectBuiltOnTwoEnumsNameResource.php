<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsIntersect;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * A test-only resource whose intersection only builds on a type name two enums the file reads both print.
 *
 * @mixin Post
 */
#[TsIntersect('{ s: StatusType }', '@/types/s')]
class IntersectBuiltOnTwoEnumsNameResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['a' => $this->status, 'b' => CrmStatus::Lead];
    }
}
