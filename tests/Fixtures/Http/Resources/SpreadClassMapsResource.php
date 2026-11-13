<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * A test-only resource spreading two maps of models in a row, nested in a value: of two classes, of one twice, and of
 * two classes one name spells.
 *
 * @mixin Post
 */
final class SpreadClassMapsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array<string, User> $people */
        $people = $request->input('people', []);
        /** @var array<string, Comment> $notes */
        $notes = $request->input('notes', []);
        /** @var array<string, CrmUser> $contacts */
        $contacts = $request->input('contacts', []);

        return [
            'row' => [...$people, ...$notes],
            'twice' => [...$people, ...$people],
            'alike' => [...$people, ...$contacts],
        ];
    }
}
