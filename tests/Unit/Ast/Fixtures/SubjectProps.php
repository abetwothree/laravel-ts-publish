<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Workbench\App\Models\Post;

/**
 * A non-resource subject: no backing model, so every `$this->prop` must resolve from the class's
 * own declared properties (subject mode) rather than from a model's columns.
 */
final class SubjectProps
{
    public int $teamId;

    /** @var list<string> */
    public array $tags;

    public Post $post;

    private array $items;

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'team' => $this->teamId,
            'tags' => $this->tags,
            'title' => $this->post->title,
            'count' => count($this->items),
        ];
    }

    /** @return array<string, mixed> */
    public function subject(): array
    {
        return ['post' => $this->post];
    }

    /** @return array<string, mixed> */
    public function unresolvableChain(): array
    {
        return ['nope' => $this->tags->missing];
    }

    public function computedName(): string
    {
        return 'order.'.$this->teamId;
    }

    public function literalPastAClosure(): string
    {
        $fallback = function (): string {
            return 'closure.literal';
        };

        $fallback();

        return 'past.the.closure';
    }

    public function twoLiteralReturns(): string
    {
        if ($this->teamId > 0) {
            return 'many';
        }

        return 'one';
    }

    public function noReturn(): void {}
}
