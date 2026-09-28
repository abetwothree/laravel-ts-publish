<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;

/** A docblock-filled `_tag` signature from one props branch, beside `_tag` keys from another or from a cast. */
class ControllerWithTagSignatureBranches
{
    /** Two render calls of one component, merged into one page type. */
    public function show(): Response
    {
        if (request()->has('a')) {
            return Inertia::render('Tags/Show', [...$this->tags(), 'id' => 1]);
        }

        return Inertia::render('Tags/Show', ['price_tag' => 5, 'id' => 2]);
    }

    /** A ternary of two props literals, one holding a resource-typed `_tag` key. */
    public function ternary(): Response
    {
        return Inertia::render('Tags/Ternary', request()->has('a')
            ? [...$this->tags()]
            : ['price_tag' => new PostResource(Post::query()->firstOrFail())]);
    }

    /** The method's own cast adds a `_tag` key the props never have. */
    #[TsCasts(['extra_tag' => 'boolean'])]
    public function cast(): Response
    {
        return Inertia::render('Tags/Cast', [...$this->tags()]);
    }

    /** The method's cast retypes the resource-typed `_tag` key of the other ternary arm. */
    #[TsCasts(['price_tag' => 'number'])]
    public function ternaryCast(): Response
    {
        return Inertia::render('Tags/TernaryCast', request()->has('a')
            ? [...$this->tags()]
            : ['price_tag' => new PostResource(Post::query()->firstOrFail())]);
    }

    /** The method's cast names `undefined` only inside a string literal, beside another render's optional key. */
    #[TsCasts(['state_tag' => "'undefined'"])]
    public function undefinedLiteral(): Response
    {
        if (request()->has('a')) {
            return Inertia::render('Tags/UndefinedLiteral', [...$this->tags()]);
        }

        return Inertia::render('Tags/UndefinedLiteral', ['price_tag' => 5]);
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
