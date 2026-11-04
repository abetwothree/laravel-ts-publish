<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HelperMembers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\User;

/** A comment's user, bare and through a documented helper, in place and returned by a bare `: array` helper. */
final class CommentHelperResource extends JsonResource
{
    /** @var Comment */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'in_user' => ['user' => $this->user, 'title' => (string) $this->content],
            'helper_user' => $this->withUser(),
            'in_calls' => ['x' => $this->documented(), 'n' => 1],
            'helper_calls' => $this->calls(),
        ];
    }

    /** The comment's user beside its text. */
    private function withUser(): array
    {
        return ['user' => $this->user, 'title' => (string) $this->content];
    }

    /** A call to a helper whose `@return` shape names a model, beside a number. */
    private function calls(): array
    {
        return ['x' => $this->documented(), 'n' => 1];
    }

    /** @return array{owner: User, n: int} */
    private function documented(): array
    {
        return ['owner' => $this->user, 'n' => 1];
    }
}
