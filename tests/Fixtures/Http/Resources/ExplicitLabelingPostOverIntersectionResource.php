<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\HasLabel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\LabelingPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A resource whose attribute names a model that is every member of the intersection its mixin names.
 *
 * @mixin Post&HasLabel
 */
#[TsResource(model: LabelingPost::class)]
final class ExplicitLabelingPostOverIntersectionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'label' => $this->label(),
        ];
    }
}
