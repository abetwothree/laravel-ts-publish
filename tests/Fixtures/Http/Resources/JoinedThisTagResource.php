<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\LabelledValue;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\LooseName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\ValueObjects\CartTotals;

/**
 * A parameter an inline `@var` on the last statement of an `if` block retypes over `$this`, read past that block
 * through members the resource or the value object does not declare or does not type, and members the post answers.
 *
 * @mixin Post
 */
final class JoinedThisTagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'static_name' => $this->whenLoaded('author', function (User $a) {
                if ($this->is_pinned) {
                    /** @var static $a */
                    $a = $this;
                }

                return $a->name;
            }),
            'self_name' => $this->whenLoaded('author', function (User $a) {
                if ($this->is_pinned) {
                    /** @var self $a */
                    $a = $this;
                }

                return $a->name;
            }),
            'parent_name' => $this->whenLoaded('author', function (User $a) {
                if ($this->is_pinned) {
                    /** @var parent $a */
                    $a = $this;
                }

                return $a->name;
            }),
            'totals_name' => $this->whenLoaded('author', function (User $a) {
                if ($this->is_pinned) {
                    /** @var CartTotals $a */
                    $a = $this;
                }

                return $a->name;
            }),
            'static_title' => $this->whenLoaded('author', function (User $a) {
                if ($this->is_pinned) {
                    /** @var static $a */
                    $a = $this;
                }

                return $a->title;
            }),
            'static_key' => $this->whenLoaded('author', function (User $a) {
                if ($this->is_pinned) {
                    /** @var static $a */
                    $a = $this;
                }

                return $a->getKey();
            }),
            'totals_key' => $this->whenLoaded('author', function (User $a) {
                if ($this->is_pinned) {
                    /** @var CartTotals $a */
                    $a = $this;
                }

                return $a->getKey();
            }),
            'totals_year' => $this->whenLoaded('author', function (User $a) {
                if ($this->is_pinned) {
                    /** @var CartTotals $a */
                    $a = $this;
                }

                return $a->created_at?->format('Y');
            }),
            'loose_name' => $this->whenLoaded('author', function (User $a) {
                if ($this->is_pinned) {
                    /** @var LooseName $a */
                    $a = $this;
                }

                return $a->name;
            }),
            'self_label' => $this->labelOf(new LabelledValue),
        ];
    }

    /** A label no declaration types. */
    public function label()
    {
        return unserialize(serialize(1));
    }

    /** The label of the value passed, or of this resource, which a `self` tag names. */
    private function labelOf(LabelledValue $a)
    {
        if ($this->is_pinned) {
            /** @var self $a */
            $a = $this;
        }

        return $a->label();
    }
}
