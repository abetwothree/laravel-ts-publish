<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/** A resource folding fills whose override's name survives only in a literal, or beside both users in either order. */
final class DocShapeFoldedOrphanResource extends JsonResource
{
    /** @var Comment */
    public $resource;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'literal_override' => [...$this->literalOverride()],
            'literal_crm' => [...$this->literalCrm()],
            'triple' => [...$this->triple()],
            'triple_reordered' => [...$this->tripleReordered()],
        ];
    }

    /**
     * The override beside the model, and the literal `'User'`.
     *
     * @return array{k: Person|User, kind: 'User'}
     */
    private function literalOverride(): array
    {
        $decoded = json_decode((string) $this->content);

        return ['k' => $decoded, 'kind' => $decoded];
    }

    /**
     * The override beside the model, the literal `'User'` and the CRM user.
     *
     * @return array{k: Person|User, kind: 'User', c: CrmUser}
     */
    private function literalCrm(): array
    {
        $decoded = json_decode((string) $this->content);

        return ['k' => $decoded, 'kind' => $decoded, 'c' => $decoded];
    }

    /**
     * The override beside the model, then the CRM user, then the model.
     *
     * @return array{k: Person|User, c: CrmUser, m: User}
     */
    private function triple(): array
    {
        $decoded = json_decode((string) $this->content);

        return ['k' => $decoded, 'c' => $decoded, 'm' => $decoded];
    }

    /**
     * The same members, the model before the CRM user.
     *
     * @return array{k: Person|User, m: User, c: CrmUser}
     */
    private function tripleReordered(): array
    {
        $decoded = json_decode((string) $this->content);

        return ['k' => $decoded, 'm' => $decoded, 'c' => $decoded];
    }
}
