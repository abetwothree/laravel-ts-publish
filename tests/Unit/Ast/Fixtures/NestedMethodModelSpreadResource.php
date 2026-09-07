<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Regression (Task 32 review, IMPORTANT-1): a trait/own-method spread reached from inside a
 * NESTED array must not flatten just because the top-level flatten branch exists — $topLevel
 * has to thread through analyzeThisMethodSpread(), not reset to true on re-entry.
 */
class NestedMethodModelSpreadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'meta' => [...$this->extraFields(), 'y' => 2],
        ];
    }

    /** @return array<string, mixed> */
    protected function extraFields(): array
    {
        return [...$this->user->toArray(), 'z' => 3];
    }
}
