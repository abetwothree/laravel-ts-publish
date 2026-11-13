<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReviewSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;

/**
 * Sends a nullable summary, a user-or-post subject and each row's nullable note under a `@return` docblock that leaves
 * out an arm the body reads: the `null` or the post (GH-154).
 *
 * @property-read ReviewSummary $resource
 */
final class ReviewKeptArmsResource extends JsonResource
{
    /** @return array{summary: string, subject: User, notes: list<array{note: string}>} */
    public function toArray(Request $request): array
    {
        return [
            'summary' => $this->resource->summary,
            'subject' => $this->resource->subject,
            'notes' => array_map(fn ($r) => ['note' => $r->note], $this->resource->rows),
        ];
    }
}
