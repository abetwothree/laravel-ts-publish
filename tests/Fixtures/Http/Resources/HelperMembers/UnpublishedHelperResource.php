<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HelperMembers;

use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\ExcludedModel;
use Workbench\App\Models\Post;

/** Models no run publishes, the `#[TsExclude]`d one and the framework's, beside a title, in place and in helpers. */
final class UnpublishedHelperResource extends JsonResource
{
    /** @var Post */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'in_excluded' => ['m' => new ExcludedModel, 'title' => (string) $this->title],
            'helper_excluded' => $this->withExcluded(),
            'in_auth_user' => ['m' => new AuthUser, 'title' => (string) $this->title],
            'helper_auth_user' => $this->withAuthUser(),
            'in_excluded_query' => ['m' => ExcludedModel::query()->first(), 'title' => (string) $this->title],
            'helper_excluded_query' => $this->withExcludedQuery(),
            'in_auth_query' => ['m' => AuthUser::query()->first(), 'title' => (string) $this->title],
            'helper_auth_query' => $this->withAuthQuery(),
            'in_auth_param' => ['m' => AuthUser::query()->firstOrFail(), 'title' => (string) $this->title],
            'helper_auth_param' => $this->withGivenUser(AuthUser::query()->firstOrFail()),
        ];
    }

    /** A new excluded model beside the title. */
    private function withExcluded(): array
    {
        return ['m' => new ExcludedModel, 'title' => (string) $this->title];
    }

    /** A new framework user beside the title. */
    private function withAuthUser(): array
    {
        return ['m' => new AuthUser, 'title' => (string) $this->title];
    }

    /** The first excluded model beside the title. */
    private function withExcludedQuery(): array
    {
        return ['m' => ExcludedModel::query()->first(), 'title' => (string) $this->title];
    }

    /** The first framework user beside the title. */
    private function withAuthQuery(): array
    {
        return ['m' => AuthUser::query()->first(), 'title' => (string) $this->title];
    }

    /** The given framework user beside the title. */
    private function withGivenUser(AuthUser $user): array
    {
        return ['m' => $user, 'title' => (string) $this->title];
    }
}
