<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Early-exit guards on a variable never written, written only before the guard, and written after it. */
final class GuardWritePostResource extends JsonResource
{
    /**
     * Spreads each helper.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $picker = new PostSourcePicker;

        return [
            ...$this->neverWritten($picker->pick()),
            ...$this->writtenBeforeGuard($picker),
            ...$this->writtenAfterGuard($picker->pick()),
        ];
    }

    /**
     * The guarded parameter is never written.
     *
     * @return array<string, mixed>
     */
    protected function neverWritten(PostScoreSource|PostLabelSource $source): array
    {
        if (! $source instanceof PostScoreSource) {
            return [];
        }

        return ['zero' => $source->value()];
    }

    /**
     * The guarded variable is written twice, both times before the guard tests it.
     *
     * @return array<string, mixed>
     */
    protected function writtenBeforeGuard(PostSourcePicker $picker): array
    {
        $source = $picker->pick();

        if ($source instanceof PostLabelSource) {
            $source = $picker->pick();
        }

        if (! $source instanceof PostScoreSource) {
            return [];
        }

        return ['before' => $source->value()];
    }

    /**
     * The guarded parameter is written after the guard, so the read no longer holds what the guard tested.
     *
     * @return array<string, mixed>
     */
    protected function writtenAfterGuard(PostScoreSource|PostLabelSource $source): array
    {
        if (! $source instanceof PostScoreSource) {
            return [];
        }

        $source = new PostLabelSource;

        return ['after' => $source->value()];
    }
}
