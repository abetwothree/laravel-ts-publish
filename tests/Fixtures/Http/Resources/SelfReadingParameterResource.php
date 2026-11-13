<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Reads, through `+`, helpers whose parameter's one write reads the parameter itself: typed, docblocked, untyped, and
 * returned whole.
 *
 * @mixin Post
 */
final class SelfReadingParameterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'shape' => $this->shape(['a' => 5]),
            'declared' => $this->declaredShape(['a' => 5]),
            'untyped' => $this->untypedShape(['a' => 5]),
            'whole' => $this->wholeShape(['a' => 5]),
        ];
    }

    /**
     * The options with a default `a`, plus `k`.
     *
     * @param  array<string, mixed>  $o
     * @return array<string, mixed>
     */
    private function shape(array $o): array
    {
        $o = ['a' => $o['a'] ?? 1, 'b' => 2];

        return ['o' => $o, 'm' => $o + ['k' => 1]];
    }

    /**
     * The options `a`, plus `k`.
     *
     * @param  array{a: int}  $o
     * @return array<string, mixed>
     */
    private function declaredShape(array $o): array
    {
        $o = ['a' => $o['a'], 'b' => 2];

        return ['m' => $o + ['k' => 1]];
    }

    /**
     * The untyped options with a default `a`, plus `k`.
     *
     * @return array<string, mixed>
     */
    private function untypedShape($o)
    {
        $o = ['a' => $o['a'] ?? 1, 'b' => 2];

        return ['m' => $o + ['k' => 1]];
    }

    /**
     * The options with a default `a`, plus `k`, returned whole.
     *
     * @param  array<string, mixed>  $o
     * @return array<string, mixed>
     */
    private function wholeShape(array $o): array
    {
        $o = ['a' => $o['a'] ?? 1, 'b' => 2];

        return $o + ['k' => 1];
    }
}
