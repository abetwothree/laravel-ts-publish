<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HelperMembers;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\DualAuthorPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Two related models that share a basename, written in place and returned by a bare `: array` helper. */
final class DualAuthorHelperResource extends JsonResource
{
    /** @var DualAuthorPost */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'in_pair' => ['a' => $this->author, 'b' => $this->crmAuthor],
            'helper_pair' => $this->authors(),
        ];
    }

    /** The workbench author and the CRM author. */
    private function authors(): array
    {
        return ['a' => $this->author, 'b' => $this->crmAuthor];
    }
}
