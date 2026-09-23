<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Reads PostSummaryReport's body, as another resource does, so the second read reuses the first's analysis. */
final class PostSummaryResource extends JsonResource
{
    /** The report, typed from its body. */
    public function toArray(Request $request): array
    {
        return ['summary' => (new PostSummaryReport)->summary()];
    }
}
