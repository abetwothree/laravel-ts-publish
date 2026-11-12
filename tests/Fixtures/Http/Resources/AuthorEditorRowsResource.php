<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\AuthorEditorRows;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Maps rows whose body types a CRM author and an app editor, two classes one name spells, beside a cache read the
 * `@return` docblock types as the app user (GH-154).
 *
 * @property-read AuthorEditorRows $resource
 */
final class AuthorEditorRowsResource extends JsonResource
{
    /** @return array{rows: list<array{by: CrmUser, ed: User, other: User|null}>} */
    public function toArray(Request $request): array
    {
        return [
            'rows' => array_map(fn ($r) => ['by' => $r->author, 'ed' => $r->editor, 'other' => cache('other')], $this->resource->rows),
        ];
    }
}
