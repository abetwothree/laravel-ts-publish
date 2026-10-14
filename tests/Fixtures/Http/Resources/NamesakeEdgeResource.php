<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Edges of a helper read as a value: a returned closure, arrow function or first-class callable, which Laravel sends as
 * `{}`, directly or through a local, a copy, `?:`, `??` or `match`; a short ternary, a body that can end without a
 * value, a nested helper, a callable string, the wrapped model, and locals that hold no callable.
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
            'via_local' => $this->formatterViaLocal(),
            'via_ternary' => $this->formatterOrTitle(),
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

    /** A closure object held in a local. */
    public function formatterViaLocal()
    {
        $format = fn () => 1;

        return $format;
    }

    /** A closure object, or the title. */
    public function formatterOrTitle()
    {
        return $this->title ? fn () => 1 : 'untitled';
    }

    /** The title, else a closure object. */
    public function titleOrFormatter()
    {
        return $this->title ?: fn () => 1;
    }

    /** The title, else a closure object, through `??`. */
    public function coalescedFormatter()
    {
        return $this->title ?? fn () => 1;
    }

    /** A closure object held in a local, checked by `?:`. */
    public function checkedFormatter()
    {
        $format = fn () => 1;

        return $format ?: 'none';
    }

    /** A closure object held in a local, on the left of `??`. */
    public function coalescedLocal()
    {
        $format = fn () => 1;

        return $format ?? 'none';
    }

    /** A closure object for an empty title, through `match`. */
    public function matchedFormatter()
    {
        return match ($this->title) {
            '' => fn () => 1,
            default => $this->title,
        };
    }

    /** A closure object copied from one local into another. */
    public function copiedFormatter()
    {
        $format = fn () => 1;
        $copy = $format;

        return $copy;
    }

    /** A first-class callable held in a local. */
    public function callableViaLocal()
    {
        $shout = strtoupper(...);

        return $shout;
    }

    /** Two locals bound to each other, a cycle the read must end. */
    public function cyclicLocals()
    {
        $first = $second;
        $second = $first;

        return $first;
    }

    /** The title, held in a local. */
    public function titleViaLocal()
    {
        $title = $this->title;

        return $title;
    }

    /** Each comment's id, mapped by a callback and held in a local. */
    public function idsViaLocal()
    {
        $ids = $this->comments->map(fn ($comment) => $comment->id)->all();

        return $ids;
    }
}
