<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Concerns\LabelsByTitle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Category;

/**
 * A category's title, through a trait's helper a post resource shares. A category has no title column.
 *
 * @mixin Category
 */
final class TitleLabelCategoryResource extends JsonResource
{
    use LabelsByTitle;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['label' => $this->label()];
    }
}
