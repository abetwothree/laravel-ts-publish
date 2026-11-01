<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;

/** Shares a dotted key whose head a spread's template-literal signature covers. */
class MiddlewareWithDottedSignatureHead
{
    /**
     * The shared props.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [...$this->tags(), 'x_tag.b' => 1];
    }

    /**
     * Keys ending in `_tag`, which only the docblock types.
     *
     * @return array<string, string>
     */
    public function tags(): array
    {
        $tags = [];

        foreach (['east', 'west'] as $name) {
            $tags["{$name}_tag"] = $this->opaque();
        }

        return $tags;
    }

    /** A value the analysis cannot type. */
    public function opaque(): mixed
    {
        return 'v';
    }
}
