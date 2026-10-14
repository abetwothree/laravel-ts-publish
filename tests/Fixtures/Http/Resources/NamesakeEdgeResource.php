<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Edges of a helper read as a value: a returned closure and arrow function, which Laravel sends as `{}`, a short
 * ternary, a body that can end without a value, a nested helper, a callable string and the wrapped model.
 *
 * @mixin Post
 */
final class NamesakeEdgeResource extends JsonResource
{
    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'closure' => $this->formatter(),
            'arrow' => $this->arrow(),
            'cond' => $this->conditional(),
            'falls' => $this->fallsOff(),
            'nested' => $this->outer(),
            'callable' => $this->callableString(),
            'wrapped' => $this->wrapped(),
        ];
    }

    /** A closure object. */
    public function formatter()
    {
        return function ($x) {
            return strtoupper($x);
        };
    }

    /** An arrow function object. */
    public function arrow(): mixed
    {
        return fn () => 1;
    }

    /** The title, or a placeholder. */
    public function conditional()
    {
        return $this->title ?: 'untitled';
    }

    /** Ends without a value on one path. */
    public function fallsOff()
    {
        if ($this->title) {
            return $this->title;
        }
    }

    /** Another untyped helper's number, plus one. */
    public function outer()
    {
        return $this->inner() + 1;
    }

    /** An untyped number. */
    public function inner()
    {
        return 41;
    }

    /** A callable string, which is still a string. */
    public function callableString()
    {
        return 'strtoupper';
    }

    /** The wrapped model, with no declaration. */
    public function wrapped()
    {
        return $this->resource;
    }
}
