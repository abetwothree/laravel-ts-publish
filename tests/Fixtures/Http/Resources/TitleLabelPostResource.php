<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Concerns\LabelsByTitle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A post's title, through a trait's helper another resource shares.
 *
 * @mixin Post
 */
final class TitleLabelPostResource extends JsonResource
{
    use LabelsByTitle;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['label' => $this->label()];
    }
}
