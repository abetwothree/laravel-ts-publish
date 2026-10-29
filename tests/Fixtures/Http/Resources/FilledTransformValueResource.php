<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * transform() calls its callback only for a filled value, so the parameter holding a nullable non-model value is not
 * null there, while its default is passed the blank value, null included. A when() condition, a ternary's test and an
 * early return prove a nullable local non-null where it runs, but a local that is only null stays null.
 *
 * @mixin Post
 */
class FilledTransformValueResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $score = $this->rating;
        $nothing = null;

        return [
            'scalar' => $this->transform($this->rating, fn ($rating) => $rating),
            'shape' => $this->transform($this->rating, fn ($rating) => ['value' => $rating]),
            'callable' => $this->transform($this->rating, $this->boxed(...)),
            'helper' => $this->transform($this->ratingOrNull(), fn ($rating) => $rating),
            'default' => $this->transform($this->rating, fn ($rating) => $rating, fn ($blank) => $blank),
            'when_proved' => $this->when($score, fn () => $score),
            'only_null' => $this->when($nothing, fn () => $nothing),
            'ternary' => $score !== null ? $score : 'none',
            'guarded' => $this->guardedRating(),
        ];
    }

    /**
     * The post's rating, or `none` while unrated, read past an early return.
     */
    private function guardedRating()
    {
        $rating = $this->rating;

        if ($rating === null) {
            return 'none';
        }

        return $rating;
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
