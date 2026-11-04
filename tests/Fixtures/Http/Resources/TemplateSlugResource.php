<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\TemplateSlug;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A template-literal override read plain, beside null, beside a string, in a list, and from a nullable helper. */
final class TemplateSlugResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'slug' => new TemplateSlug,
            'slug_ternary' => $request->boolean('x') ? new TemplateSlug : null,
            'slug_or_string' => $request->boolean('x') ? new TemplateSlug : 'z',
            'slug_list' => [new TemplateSlug, new TemplateSlug],
            'slug_helper' => $this->maybeSlug($request),
        ];
    }

    /** A slug, or null when the request does not ask for one. */
    private function maybeSlug(Request $request): ?TemplateSlug
    {
        return $request->boolean('x') ? new TemplateSlug : null;
    }
}
