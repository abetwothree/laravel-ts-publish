<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/** A resource folding a fill that spells `User` in a literal beside the model, in a file aliasing the CRM `User`. */
final class DocShapeFoldedLiteralResource extends JsonResource
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
            'literal' => [...$this->literal()],
            'pair' => [...$this->pair()],
        ];
    }

    /**
     * The literal `'User'` beside the model.
     *
     * @return array{k: array{kind: 'User', r: User}}
     */
    private function literal(): array
    {
        return ['k' => json_decode((string) $this->content)];
    }

    /**
     * The two same-basename users.
     *
     * @return array{k: User|CrmUser}
     */
    private function pair(): array
    {
        return ['k' => json_decode((string) $this->content)];
    }
}
