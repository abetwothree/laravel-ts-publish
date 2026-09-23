<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;

/** The class-level cast names `undefined` only inside a string literal, beside the docblock's optional `price_tag`. */
#[TsCasts(['state_tag' => "'undefined'"])]
class MiddlewareWithUndefinedLiteralCast
{
    /**
     * The shared props.
     *
     * @return array{price_tag?: int}
     */
    public function share(Request $request): array
    {
        return [...$this->tags(), 'price_tag' => 5];
    }

    /**
     * `_tag` keys only the docblock types.
     *
     * @return array<string, string>
     */
    public function tags(): array
    {
        $data = [];

        foreach (['east', 'west'] as $name) {
            $data["{$name}_tag"] = $this->opaque();
        }

        return $data;
    }

    /** Deliberately untyped. */
    protected function opaque()
    {
        return request()->input('x');
    }
}
