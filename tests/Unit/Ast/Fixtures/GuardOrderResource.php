<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A return before an early-exit instanceof guard and one after it, in the method body and in a closure body: the
 * guard proves its class only for what runs after it.
 */
final class GuardOrderResource extends JsonResource
{
    /**
     * Returns early before the guard, and in full after it.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $source = new PostSourcePicker()->pick();

        if ($request->boolean('early')) {
            return ['early' => $source->value()];
        }

        if (! $source instanceof PostScoreSource) {
            return [];
        }

        return [
            'late' => $source->value(),
            'deferred' => $this->when($request->boolean('deferred'), function () use ($request) {
                $inner = new PostSourcePicker()->pick();

                if ($request->boolean('early')) {
                    return $inner->value();
                }

                if (! $inner instanceof PostScoreSource) {
                    return null;
                }

                return $inner->value();
            }),
        ];
    }
}
