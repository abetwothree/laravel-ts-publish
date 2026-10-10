<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource with two return branches whose helpers cast `title` to one type, only one of them optional,
 * while its model casts `title` required.
 *
 * @mixin RequiredTitlePost
 */
class BranchOptionalCastOverRequiredModelResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($request->boolean('a')) {
            return [...$this->flagged()];
        }

        return [...$this->plain()];
    }

    /** @return array<string, mixed> */
    #[TsCasts(['title' => ['type' => 'BranchTitle', 'optional' => true]])]
    protected function flagged(): array
    {
        return ['title' => $this->title];
    }

    /** @return array<string, mixed> */
    #[TsCasts(['title' => 'BranchTitle'])]
    protected function plain(): array
    {
        return ['title' => $this->title];
    }
}
