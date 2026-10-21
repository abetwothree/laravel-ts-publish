<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\CapturedLocalClosureResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MappedProfileWrapResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UnboundClosureVariableResource;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\Profile;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;

/** What Laravel sends for one key of MappedProfileWrapResource over a team whose second member has no profile. */
function mappedProfileWrapSends(string $key): string
{
    $withProfile = (new User)->forceFill(['id' => 1, 'name' => 'a']);
    $withProfile->setRelation('profile', (new Profile)->forceFill(['id' => 5, 'user_id' => 1]));
    $without = (new User)->forceFill(['id' => 2, 'name' => 'b']);
    $without->setRelation('profile', null);
    $team = (new Team)->forceFill(['id' => 9]);
    $team->setRelation('members', new EloquentCollection([$withProfile, $without]));
    $team->setRelation('owner', $without);

    $value = new MappedProfileWrapResource($team)->toArray(request())[$key];
    $sent = new class($value) extends JsonResource
    {
        /** @return array<string, mixed> */
        public function toArray(Request $request): array
        {
            return ['k' => $this->resource];
        }
    };

    try {
        return (string) $sent->response(request())->getContent();
    } catch (Throwable) {
        return 'throws';
    }
}

// A read through a variable no writer binds goes on to the receiver handlers, which never guess the relation's model.
it('types a variable read inside whenLoaded() only from the variable\'s own binding', function () {
    expect(publishedResourceKeyTypes(UnboundClosureVariableResource::class, Post::class))->toBe([
        'unbound_prop?' => 'unknown',
        'unbound_method?' => 'unknown',
        'unbound_pluck?' => 'unknown',
        'local_post?' => 'string',
        'author_relations?' => '{ posts: Post[]; profile: Profile | null }',
        's_author' => 'User',
        's_comments' => 'Comment[]',
    ]);
});

// JsonResource::filter() recurses into arrays only: inside a Collection the null-payload wrap serializes itself.
it('adds a wrap\'s null arm only where filter() reaches the mapped items', function () {
    expect(publishedResourceKeyTypes(MappedProfileWrapResource::class, Team::class))->toBe([
        'profiles?' => '(Profile | null)[]',
        'wrapped?' => '{ profile: ProfileResource }[]',
        'wrapped_values?' => '{ profile: ProfileResource }[]',
        'wrapped_all?' => '({ profile: ProfileResource | null })[]',
        'chain_wrapped' => '{ profile: ProfileResource }[]',
        'chain_wrapped_all' => '({ profile: ProfileResource | null })[]',
        'owner_wrapped?' => '{ profile: ProfileResource | null }',
    ])
        ->and(mappedProfileWrapSends('wrapped'))->toBe('throws')
        ->and(mappedProfileWrapSends('wrapped_values'))->toBe('throws')
        ->and(mappedProfileWrapSends('chain_wrapped'))->toBe('throws')
        ->and(mappedProfileWrapSends('wrapped_all'))->toContain('{"profile":null}')
        ->and(mappedProfileWrapSends('chain_wrapped_all'))->toContain('{"profile":null}')
        ->and(mappedProfileWrapSends('owner_wrapped'))->toBe('{"data":{"k":{"profile":null}}}');
});

it('a captured outer local keeps its own binding inside a whenLoaded() closure', function () {
    expect(publishedResourceKeyTypes(CapturedLocalClosureResource::class, Post::class))->toBe([
        'captured_use?' => 'string',
        'captured_arrow?' => 'string',
    ]);
});
