<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedModelRegistry;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HelperMembers\CaseGetterHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HelperMembers\CategoryHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HelperMembers\CommentHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HelperMembers\DualAuthorHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HelperMembers\PostHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HelperMembers\TaskHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HelperMembers\UnpublishedHelperResource;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * The classes on a published key's import channels, once each in a stable order.
 *
 * @param  class-string<JsonResource>  $resource
 * @return list<class-string>
 */
function bodyFallbackChannelClasses(string $resource, string $key): array
{
    $analysis = resolve(AstEngine::class)->analyzeMethod($resource);
    $classes = array_values(array_unique([...$analysis->tokenFqcns($key), ...$analysis->wrappedEnumFqcns($key)]));
    sort($classes);

    return $classes;
}

describe('a bare `: array` helper read as a value', function () {
    test('publishes a member naming a class as its literal does in place, with the same import channels', function (string $resource, string $name, string $type) {
        $properties = new ResourceTransformer($resource)->properties;
        $inPlace = bodyFallbackChannelClasses($resource, 'in_'.$name);

        expect($properties['helper_'.$name]['type'])->toBe($type)
            ->and($properties['in_'.$name]['type'])->toBe($type)
            ->and($inPlace)->not->toBe([])
            ->and(bodyFallbackChannelClasses($resource, 'helper_'.$name))->toBe($inPlace);
    })->with([
        'a relation\'s model' => [CommentHelperResource::class, 'user', '{ user: User; title: string }'],
        'a call to a helper whose @return shape names a model' => [CommentHelperResource::class, 'calls', '{ x: { owner: User; n: number }; n: number }'],
        'a toResource() resource' => [CategoryHelperResource::class, 'r', '{ r: CategoryResource | null; title: string }'],
        'an EnumResource::make() wrap' => [PostHelperResource::class, 's', '{ s: AsEnum<typeof Status>; title: string }'],
        'a seeded parameter holding a model' => [PostHelperResource::class, 'card', '{ author: User; seen: boolean }'],
        'a cast column' => [PostHelperResource::class, 'cast', '{ v: StatusType }'],
        'a filtered relation' => [PostHelperResource::class, 'filtered', "{ author: Pick<User, 'id' | 'name'> }"],
        'relation and method reads on a typed parameter' => [TaskHelperResource::class, 'rel', '{ assignee: User | null; fresh: HelperTask | null; team: Team; team_name: string; key: number }'],
        'two models that share a basename' => [DualAuthorHelperResource::class, 'pair', '{ a: WorkbenchUser; b: CrmUser }'],
        'a payload method called on a resource the body builds' => [PostHelperResource::class, 'built', '{ author: User; title: string }'],
        'an EnumResource wrap over either of two cases' => [CaseGetterHelperResource::class, 'wrapped', '{ s: AsEnum<typeof Status> }'],
        'an EnumResource wrap over an accessor returning a case' => [CaseGetterHelperResource::class, 'given_wrap', '{ s: AsEnum<typeof Status> }'],
    ]);

    // The file imports what its in-place keys bring too; the channel equality above is what isolates each helper.
    test('imports the enum an EnumResource wrap spells as a value', function () {
        expect(new ResourceTransformer(PostHelperResource::class)->valueImports)
            ->toBe(['../../../../../../../workbench/app/enums' => ['Status']]);
    });

    test('imports each of two models that share a basename under its own alias', function () {
        expect(new ResourceTransformer(DualAuthorHelperResource::class)->typeImports)->toBe([
            '../../../../../../../workbench/app/models' => ['User as WorkbenchUser'],
            '../../../../../../../workbench/crm/models' => ['User as CrmUser'],
        ]);
    });

    // P9: the literal a plain backed case sends is more specific than the enum's type its literal reads in place.
    test('keeps the literal a plain backed enum case sends, alone, listed or from a getter, with no channel', function (string $resource, string $name, string $type, string $inPlace) {
        $properties = new ResourceTransformer($resource)->properties;

        expect($properties['helper_'.$name]['type'])->toBe($type)
            ->and($properties['in_'.$name]['type'])->toBe($inPlace)
            ->and(bodyFallbackChannelClasses($resource, 'helper_'.$name))->toBe([]);
    })->with([
        'one case' => [PostHelperResource::class, 'single', '{ v: 0 }', '{ v: StatusType }'],
        'a list literal' => [PostHelperResource::class, 'inline', '{ v: (0 | 1)[] }', '{ v: StatusType[] }'],
        'a class constant listing cases' => [PostHelperResource::class, 'constant', '{ v: (0 | 1)[] }', '{ v: StatusType[] }'],
        'a collected list' => [PostHelperResource::class, 'collected', '{ v: 0[] }', '{ v: StatusType[] }'],
        'an accessor whose getter returns one' => [CaseGetterHelperResource::class, 'default', '{ v: 0; n: number }', '{ v: StatusType; n: number }'],
        'an accessor whose getter returns a shape holding one' => [CaseGetterHelperResource::class, 'shape', '{ v: { s: 0; n: number }; n: number }', '{ v: { s: StatusType; n: number }; n: number }'],
        'that accessor read on a model a typed parameter holds' => [CaseGetterHelperResource::class, 'given', '{ v: 0 }', '{ v: StatusType }'],
    ]);

    // A method read as a value declines whole when it names such a model; only the member naming it gives way.
    test('leaves a member naming a model the run does not publish unknown beside its typed sibling', function (string $name, bool $publishedSet) {
        if ($publishedSet) {
            PublishedModelRegistry::register([Post::class]);
        }

        $properties = new ResourceTransformer(UnpublishedHelperResource::class)->properties;

        expect($properties['helper_'.$name]['type'])->toBe('{ m: unknown; title: string }')
            ->and($properties['in_'.$name]['type'])->toBe('{ m: unknown; title: string }');
    })->with([
        'a new excluded model' => ['excluded', true],
        'a new framework user' => ['auth_user', true],
        'an excluded model\'s query' => ['excluded_query', true],
        'a framework user\'s query' => ['auth_query', true],
        'a framework user a typed parameter holds, with no published set' => ['auth_param', false],
    ]);
});
