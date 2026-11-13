<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReviewBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Sends a template key beside a member its pattern covers, a value only a ternary's `null` arm types under a vague and
 * a list member, and one that is always `null` (GH-154).
 *
 * @property-read ReviewBox $resource
 */
final class ReviewSignatureNullsResource extends JsonResource
{
    /**
     * @return array{vagueNull: list<array{tags: list<mixed>}>, covered: list<array{by: CrmUser, x_tag: string}>,
     *     listNull: list<array{tags: list<int>}>, literalNull: list<array{tags: list<int>}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'vagueNull' => array_map(fn ($r) => ['tags' => $r->n > 0 ? cache('t') : null], $this->resource->rows),
            'covered' => array_map(fn ($r) => ['by' => $r->author, "{$r->title}_tag" => $r->n], $this->resource->rows),
            'listNull' => array_map(fn ($r) => ['tags' => $r->n > 0 ? cache('t') : null], $this->resource->rows),
            'literalNull' => array_map(fn ($r) => ['tags' => null], $this->resource->rows),
        ];
    }
}
