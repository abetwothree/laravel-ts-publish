<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ConditionableBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/** A test-only post resource reading Conditionable::when() on boxes it holds, one maybe null, beside its own when(). */
final class ConditionableBoxResource extends JsonResource
{
    /** @var Post */
    public $resource;

    private ConditionableBox $box;

    private ?ConditionableBox $spare = null;

    /** Wraps the post and holds a box. */
    public function __construct(Post $resource)
    {
        parent::__construct($resource);
        $this->box = new ConditionableBox;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'box_when' => $this->box->when($this->resource->is_pinned, fn () => 'boxed'),
            'box_default' => $this->box->when($this->resource->is_pinned, fn () => 'a', fn () => 'b'),
            'own_when' => $this->when($this->resource->is_pinned, fn () => 'active'),
            'spare' => $this->spare?->when($this->resource->is_pinned, fn () => 'spare'),
        ];
    }
}
