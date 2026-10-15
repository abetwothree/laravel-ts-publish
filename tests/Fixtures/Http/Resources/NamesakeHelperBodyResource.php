<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * Helpers read as values: a scalar, a passed argument, a list literal, list pipelines and returned variables, beside
 * five `whenHas()` callbacks, three of which need rules of their own.
 *
 * @mixin Post
 */
final class NamesakeHelperBodyResource extends JsonResource
{
    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'str_static' => $this->whenHas('title', fn ($t) => Str::upper($t)),
            'find' => $this->whenHas('title', fn ($t) => User::find(1)),
            'helper_untyped' => $this->whenHas('title', fn ($t) => $this->shoutUntyped($t)),
            'fcc_method' => $this->whenHas('title', $this->shout(...)),
            'keys' => $this->whenHas('options', fn ($o) => collect($o)->keys()->all()),
            'shout_untyped' => $this->shoutUntyped($this->title),
            'echoed_title' => $this->echoed($this->title),
            'echoed_count' => $this->echoed($this->comments->count()),
            'stages' => $this->stages(),
            'history' => $this->history(),
            'ids' => $this->ids(),
            'untyped_title' => $this->untypedTitle(),
            'closure_first' => $this->closureFirst(),
            'mixed_meta' => $this->mixedMeta(),
        ];
    }

    /** A typed shout. */
    private function shout(string $t): string
    {
        return strtoupper($t);
    }

    /** An untyped shout. */
    private function shoutUntyped($t)
    {
        return strtoupper($t);
    }

    /** Whatever the call passes, with no declaration. */
    private function echoed($value)
    {
        return $value;
    }

    /** A list literal behind a vague array. */
    private function stages(): array
    {
        return ['a', 'b'];
    }

    /** A pipeline behind a vague array. */
    private function history(): array
    {
        return $this->comments->map(fn ($c) => $c->content)->values()->all();
    }

    /** A pluck behind a vague array. */
    private function ids(): array
    {
        return $this->comments->pluck('id')->all();
    }

    /** An untyped attribute read. */
    private function untypedTitle()
    {
        return $this->title;
    }

    /** A closure's return ahead of the variable the method returns. */
    private function closureFirst(): array
    {
        $active = $this->comments->filter(function ($comment) {
            return $comment->id > 1;
        });
        $meta = ['title' => $this->title, 'active' => $active->count()];

        return $meta;
    }

    /** A literal return beside a variable one. */
    private function mixedMeta(): array
    {
        if ($this->title === '') {
            return ['a' => 1];
        }

        $meta = ['a' => 2, 'b' => 'x'];

        return $meta;
    }
}
