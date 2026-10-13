<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AbstractKeyedModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Top-level `only()` spreads on receivers that hold no one concrete model, so none flattens the model's keys: a
 * nullsafe call, a morphTo relation, a to-many relation and an abstract model.
 *
 * @mixin Post
 */
final class ReceiverOnlySpreadDeclinesResource extends JsonResource
{
    public AbstractKeyedModel $owner;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['x' => 1];
    }

    /**
     * @return array<string, mixed>
     */
    public function nullsafe(): array
    {
        return [...$this->author?->only(['id', 'name']), 'x' => 1];
    }

    /**
     * Read with an Image backing, whose reviewable() is a morphTo of two models.
     *
     * @return array<string, mixed>
     */
    public function morphed(): array
    {
        return [...$this->reviewable->only(['id']), 'x' => 1];
    }

    /**
     * @return array<string, mixed>
     */
    public function toMany(): array
    {
        return [...$this->comments->only([1]), 'x' => 1];
    }

    /**
     * @return array<string, mixed>
     */
    public function abstractModel(): array
    {
        return [...$this->owner->only(['id']), 'x' => 1];
    }
}
