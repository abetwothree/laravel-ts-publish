<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\CountedPost;
use Countable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Reads a value past early-exit guards for `Countable` and for Post, which does not implement it, in both orders, and
 * a parameter declared `Countable` past a guard for Post. It wraps a CountedPost, a Post that is also Countable, and
 * names no model, so only the guards type the reads.
 *
 * @see CountedPost
 */
class InterfaceGuardedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->interfaceThenClass($this->resource),
            ...$this->classThenInterface($this->resource),
            ...$this->declaredInterface($this->resource),
        ];
    }

    /**
     * The title, past a guard for the interface and then one for the class.
     *
     * @return array<string, mixed>
     */
    private function interfaceThenClass(mixed $value): array
    {
        if (! $value instanceof Countable) {
            return [];
        }

        if (! $value instanceof Post) {
            return [];
        }

        return ['interface_then_class' => $value->title];
    }

    /**
     * The title, past a guard for the class and then one for the interface.
     *
     * @return array<string, mixed>
     */
    private function classThenInterface(mixed $value): array
    {
        if (! $value instanceof Post) {
            return [];
        }

        if (! $value instanceof Countable) {
            return [];
        }

        return ['class_then_interface' => $value->title];
    }

    /**
     * The title of a parameter declared as the interface, past a guard for the class.
     *
     * @return array<string, mixed>
     */
    private function declaredInterface(Countable $value): array
    {
        if (! $value instanceof Post) {
            return [];
        }

        return ['declared_interface' => $value->title];
    }
}
