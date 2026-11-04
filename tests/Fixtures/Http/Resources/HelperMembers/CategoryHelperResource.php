<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HelperMembers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Category;

/** A category's parent as the resource `toResource()` builds, in place and returned by a bare `: array` helper. */
final class CategoryHelperResource extends JsonResource
{
    /** @var Category */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'in_r' => ['r' => $this->parent?->toResource(), 'title' => (string) $this->name],
            'helper_r' => $this->withParentResource(),
        ];
    }

    /** The parent's resource beside the category's name. */
    private function withParentResource(): array
    {
        return ['r' => $this->parent?->toResource(), 'title' => (string) $this->name];
    }
}
