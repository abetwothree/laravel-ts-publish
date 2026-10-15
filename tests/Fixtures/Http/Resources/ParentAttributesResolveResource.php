<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource with an `$attributes` property, which JsonResource's `resolve()` sends in place of `toArray()`
 * from Laravel 12.45, under a `resolve()` that builds on `parent::resolve()`.
 *
 * @mixin Tag
 */
class ParentAttributesResolveResource extends JsonResource
{
    /** @var array<string, mixed> */
    public $attributes = ['label' => 'x'];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }

    /** @return array<string, mixed> */
    public function resolve($request = null): array
    {
        return [...parent::resolve($request), 'resolved' => true];
    }
}
