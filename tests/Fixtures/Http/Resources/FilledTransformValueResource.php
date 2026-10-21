<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * transform() calls its callback only for a filled value, so the parameter holding a nullable non-model value is not
 * null there, while its default is passed the blank value, null included. when() runs its value only for a truthy
 * condition, so a nullable local it tests is not null in the value either.
 *
 * @mixin Post
 */
class FilledTransformValueResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $score = $this->rating;

        return [
            'scalar' => $this->transform($this->rating, fn ($rating) => $rating),
            'shape' => $this->transform($this->rating, fn ($rating) => ['value' => $rating]),
            'callable' => $this->transform($this->rating, $this->boxed(...)),
            'helper' => $this->transform($this->ratingOrNull(), fn ($rating) => $rating),
            'default' => $this->transform($this->rating, fn ($rating) => $rating, fn ($blank) => $blank),
            'when_proved' => $this->when($score, fn () => $score),
        ];
    }

    /**
     * The value under a `value` key.
     *
     * @return array<string, mixed>
     */
    private function boxed($value): array
    {
        return ['value' => $value];
    }

    /**
     * The post's rating, null while unrated.
     */
    private function ratingOrNull(): ?string
    {
        return $this->rating;
    }
}
