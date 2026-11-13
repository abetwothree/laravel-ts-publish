<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

/**
 * Reads, wraps and maps the cases CaseMacros' macros return; `caseHelper` calls this resource's own vague helper.
 *
 * @mixin Post
 */
class CaseMacroResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'draft' => $this->caseDraft(),
            'draft_resource' => EnumResource::make($this->caseDraft()),
            'outer_resource' => EnumResource::make($this->caseOuter()),
            'draft_values' => array_map(fn ($s) => $s->value, [$this->caseDraft()]),
            'helper_resource' => EnumResource::make($this->caseHelper()),
            'helper_values' => array_map(fn ($s) => $s->value, [$this->caseHelper()]),
        ];
    }

    /** A case behind a mixed declaration. */
    protected function draft(): mixed
    {
        return Status::Draft;
    }
}
