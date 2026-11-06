<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\NumberedName;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\ValueObjects\CartTotals;

/**
 * Callback parameters an inline `@var` on the last statement of an `if` block retypes to an interface, the abstract
 * `Model` or an object other than a model, read past that block through a property, a method or a chain only the old
 * model answers, and through a property a plain object types too.
 *
 * @mixin Post
 */
final class JoinedLastStatementClassVarResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'auth_name' => $this->whenLoaded('author', function (User $a) {
                if ($this->is_pinned) {
                    /** @var Authenticatable $a */
                    $a = $this->opaque($this->author);
                }

                return $a->name;
            }),
            'model_email' => $this->whenLoaded('author', function (User $a) {
                if ($this->is_pinned) {
                    /** @var Model $a */
                    $a = $this->opaque($this->author);
                }

                return $a->email;
            }),
            'object_name' => $this->whenLoaded('author', function (User $a) {
                if ($this->is_pinned) {
                    /** @var CartTotals $a */
                    $a = $this->opaque(new CartTotals(1.5, true, 2));
                }

                return $a->name;
            }),
            'object_name_untyped' => $this->whenLoaded('author', function ($a) {
                if ($this->is_pinned) {
                    /** @var CartTotals $a */
                    $a = $this->opaque(new CartTotals(1.5, true, 2));
                }

                return $a->name;
            }),
            'numbered_name' => $this->whenLoaded('author', function (User $a) {
                if ($this->is_pinned) {
                    /** @var NumberedName $a */
                    $a = $this->opaque(new NumberedName);
                }

                return $a->name;
            }),
            'auth_key' => $this->whenLoaded('author', function (User $a) {
                if ($this->is_pinned) {
                    /** @var Authenticatable $a */
                    $a = $this->opaque($this->author);
                }

                return $a->getKey();
            }),
            'auth_year' => $this->whenLoaded('author', function (User $a) {
                if ($this->is_pinned) {
                    /** @var Authenticatable $a */
                    $a = $this->opaque($this->author);
                }

                return $a->created_at?->format('Y');
            }),
        ];
    }

    /** A value no rule types. */
    private function opaque(mixed $value): mixed
    {
        return unserialize(serialize($value));
    }
}
