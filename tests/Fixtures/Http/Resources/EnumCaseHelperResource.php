<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\EnumCaseHelperService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

/**
 * Reads, wraps and maps the plain enum cases helpers with a vague declaration return, directly or through another
 * helper, a local, a closure or a service.
 *
 * @mixin Post
 */
class EnumCaseHelperResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $outer = $this->outer();
        $service = new EnumCaseHelperService;

        return [
            'draft' => $this->draft(),
            'draft_resource' => EnumResource::make($this->draft()),
            'draft_list' => EnumResource::collection([$this->draft()]),
            'outer_resource' => EnumResource::make($this->outer()),
            'outer_list' => EnumResource::collection([$this->outer()]),
            'outer_drafts_resource' => EnumResource::collection($this->outerDrafts()),
            'local_outer_resource' => EnumResource::make($outer),
            'when_outer_resource' => $this->when($request->boolean('wrap'), fn () => EnumResource::make($this->outer())),
            'service_outer_resource' => EnumResource::make($service->outer()),
            'tagged' => $this->tagged(),
            'mapped_values' => array_map(fn ($d) => $d->value, $this->drafts()),
            'mapped_value' => array_map(fn ($d) => $d->value, [$this->draft()]),
            'mapped_resources' => array_map(fn ($d) => EnumResource::make($d), [$this->draft()]),
            'collected_values' => collect([$this->draft()])->map(fn ($d) => $d->value)->all(),
            'collected_icons' => collect([$this->draft()])->map(fn ($d) => $d->icon())->all(),
            'collected_resources' => collect([$this->draft()])->map(fn ($d) => EnumResource::make($d))->all(),
            'collected_outer_resources' => collect([$this->outer()])->map(fn ($d) => EnumResource::make($d))->all(),
        ];
    }

    /** A case behind a mixed declaration. */
    protected function draft(): mixed
    {
        return Status::Draft;
    }

    /** The case another vague helper returns. */
    protected function outer(): mixed
    {
        return $this->draft();
    }

    /** Two cases behind a bare array declaration. */
    protected function drafts(): array
    {
        return [Status::Draft, Status::Published];
    }

    /** The cases another vague helper returns. */
    protected function outerDrafts(): mixed
    {
        return $this->drafts();
    }

    /** Wraps of a helper's case and of another helper's, in a shape. */
    protected function tagged(): array
    {
        return ['t' => EnumResource::make($this->draft()), 'o' => EnumResource::make($this->outer())];
    }
}
