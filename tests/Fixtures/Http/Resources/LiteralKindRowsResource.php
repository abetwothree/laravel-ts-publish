<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\CrmAuthoredRows;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Maps rows whose callback returns a string literal the body reads as `string`, under a `@return` docblock that spells
 * the literal (GH-154).
 *
 * @property-read CrmAuthoredRows $resource
 */
final class LiteralKindRowsResource extends JsonResource
{
    /** @return array{rows: list<array{kind: 'User', by: CrmUser, other: User|null}>} */
    public function toArray(Request $request): array
    {
        return [
            'rows' => array_map(fn ($r) => ['kind' => 'User', 'by' => $r->author, 'other' => cache('other')], $this->resource->rows),
        ];
    }
}
