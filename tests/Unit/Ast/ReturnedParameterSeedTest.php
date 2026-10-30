<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\InPlaceSpreadResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\DecoratingChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\InPlaceRewrittenParameter;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\KeyKeepingRewrites;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\MaybeRewrittenParameter;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReplacedParameter;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\SeededEnumMembers;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\SeededRows;
use Illuminate\Http\Request;
use Workbench\App\Http\Resources\MergedPostResource;
use Workbench\App\Models\Post;
use Workbench\App\ValueObjects\GuardedLinkPayload;

it('joins a helper\'s writes onto the shape of the array it is passed', function () {
    $flagged = new GuardedLinkPayload;
    $flagged->flag = true;

    expect(analyzedKeyTypes(GuardedLinkPayload::class))->toBe([
        'id' => 'number', 'name' => 'string', 'url' => 'string | null', 'disabled' => 'boolean',
    ])->and(json_encode(new GuardedLinkPayload()->toArray()))
        ->toBe('{"id":1,"name":"x","url":"https:\/\/example.com","disabled":false}')
        ->and(json_encode($flagged->toArray()))->toBe('{"id":1,"name":"x","url":null,"disabled":true}');
});

it('seeds a parameter read as a value, a += and a declared shape', function () {
    expect(analyzedKeyTypes(GuardedLinkPayload::class, 'values'))->toBe([
        'nested' => '{ id: number; url: string | null; disabled: boolean }',
        'seen' => '{ id: number; seen: boolean }',
        'declared' => '{ id: number; name: string; extra: boolean }',
        'opaque' => '{ url?: null; disabled?: boolean; [key: string]: unknown }',
        'rebuilt' => '{ fresh: number; more: boolean }',
    ]);
});

it('starts from the helper\'s own whole write, not the argument', function () {
    expect(analyzedKeyTypes(GuardedLinkPayload::class, 'values')['rebuilt'])->toBe('{ fresh: number; more: boolean }')
        ->and(json_encode(new GuardedLinkPayload()->values()['rebuilt']))->toBe('{"fresh":1,"more":true}');
});

// The decoded array's own keys stay unread, but the argument's `id` never reaches the return.
it('seeds nothing past a top-level whole write the walk cannot read', function () {
    expect(analyzedKeyTypes(ReplacedParameter::class))->toBe(['x' => 'number'])
        ->and(json_encode(new ReplacedParameter()->toArray()))->toBe('{"k":1,"x":1}');
});

it('seeds nothing past a top-level unset() of the parameter', function () {
    expect(analyzedKeyTypes(ReplacedParameter::class, 'values')['cleared'])->toBe('{ x: number }')
        ->and(json_encode(new ReplacedParameter()->values()['cleared']))->toBe('{"x":1}');
});

// Each leaves keys the walk cannot read, a known gap, but never the argument's `id`.
it('seeds nothing past a top-level whole write the walk does not read as an assignment', function () {
    expect(analyzedKeyTypes(ReplacedParameter::class, 'values'))->toMatchArray([
        'destructured' => '{ x: number }',
        'sorted' => '{ x: number }',
        'matched' => '{ x: number }',
        'looped' => '{ x: number }',
    ])->and(json_encode(array_intersect_key(
        new ReplacedParameter()->values(),
        array_flip(['destructured', 'sorted', 'matched', 'looped']),
    )))->toBe(
        '{"destructured":{"k":1,"x":1},"sorted":{"0":1,"x":1},"matched":{"0":"a","k":"a","1":"a","x":1},'
        .'"looped":{"k":1,"x":1}}',
    );
});

// The walk does not read an assignment inside a condition, so `k` stays unread there, but `id` never comes back.
it('seeds nothing past an assignment inside another expression', function () {
    $values = new ReplacedParameter()->values();

    expect(analyzedKeyTypes(ReplacedParameter::class, 'values'))->toMatchArray([
        'chained' => '{ k: number; x: number }',
        'inCondition' => 'never[] | { x: number }',
    ])->and(json_encode($values['chained']))->toBe('{"k":1,"x":1}')
        ->and(json_encode($values['inCondition']))->toBe('{"k":1,"x":1}');
});

// A rewrite that keeps every key leaves them required, and one that may bring keys opens the shape, but for an
// assignment inside another expression, which the walk does not read. A passed array rewritten in place gets no seed.
it('keeps the keys required past a rewrite in a block that keeps every key', function () {
    expect(analyzedKeyTypes(KeyKeepingRewrites::class))->toBe([
        'merge_if' => '{ id: number; [key: string]: unknown }',
        'merge_loop' => '{ id: number; [key: string]: unknown }',
        'merge_recursive_if' => '{ id: number; [key: string]: unknown }',
        'merge_recursive_loop' => '{ id: number; [key: string]: unknown }',
        'replace_if' => '{ id: number; [key: string]: unknown }',
        'replace_loop' => '{ id: number; [key: string]: unknown }',
        'replace_recursive_if' => '{ id: number; [key: string]: unknown }',
        'replace_recursive_loop' => '{ id: number; [key: string]: unknown }',
        'arr_add_if' => '{ id: number; [key: string]: unknown }',
        'arr_add_loop' => '{ id: number; [key: string]: unknown }',
        'arr_set_if' => '{ id: number; [key: string]: unknown }',
        'arr_set_loop' => '{ id: number; [key: string]: unknown }',
        'plus_if' => '{ id: number; [key: string]: unknown }',
        'plus_loop' => '{ id: number; [key: string]: unknown }',
        'plus_assign_if' => '{ id: number; [key: string]: unknown }',
        'plus_assign_loop' => '{ id: number; [key: string]: unknown }',
        'ref_foreach_if' => '{ id: number }',
        'ref_foreach_loop' => '{ id: number }',
        'walk_if' => '{ id: number }',
        'walk_loop' => '{ id: number }',
        'walk_recursive_if' => '{ id: number }',
        'walk_recursive_loop' => '{ id: number }',
        'plus_inline_if' => '{ id: number }',
        'merge_other_if' => '{ id?: number; [key: string]: unknown }',
        'plus_other_if' => '{ id?: number; [key: string]: unknown }',
        'param_merge_if' => '{ id: number; x: number; [key: string]: unknown }',
        'param_walk_if' => '{ x: number }',
    ])->and(json_encode(new KeyKeepingRewrites()->toArray()))->toBe(
        '{"merge_if":{"id":1},"merge_loop":{"id":1},"merge_recursive_if":{"id":1},"merge_recursive_loop":{"id":1},'
        .'"replace_if":{"id":1},"replace_loop":{"id":1},"replace_recursive_if":{"id":1},"replace_recursive_loop":{"id":1},'
        .'"arr_add_if":{"id":1},"arr_add_loop":{"id":1},"arr_set_if":{"id":1},"arr_set_loop":{"id":1},"plus_if":{"id":1},'
        .'"plus_loop":{"id":1},"plus_assign_if":{"id":1},"plus_assign_loop":{"id":1},"ref_foreach_if":{"id":1},'
        .'"ref_foreach_loop":{"id":1},"walk_if":{"id":1},"walk_loop":{"id":1},"walk_recursive_if":{"id":1},'
        .'"walk_recursive_loop":{"id":1},"plus_inline_if":{"id":1},"merge_other_if":{"id":1},"plus_other_if":{"id":1},'
        .'"param_merge_if":{"id":1,"x":1},"param_walk_if":{"id":1,"x":1}}',
    )->and(json_encode(new KeyKeepingRewrites(true, [1, 2])->toArray()))->toBe(
        '{"merge_if":{"id":1,"m":1},"merge_loop":{"id":1,"m":1},"merge_recursive_if":{"id":1,"m":1},'
        .'"merge_recursive_loop":{"id":1,"m":[1,1]},"replace_if":{"id":2},"replace_loop":{"id":2},'
        .'"replace_recursive_if":{"id":2},"replace_recursive_loop":{"id":2},"arr_add_if":{"id":1,"k":1},'
        .'"arr_add_loop":{"id":1,"k":1},"arr_set_if":{"id":1,"k":1},"arr_set_loop":{"id":1,"k":1},"plus_if":{"id":1,"p":1},'
        .'"plus_loop":{"id":1,"p":1},"plus_assign_if":{"id":1,"e":1},"plus_assign_loop":{"id":1,"e":1},'
        .'"ref_foreach_if":{"id":2},"ref_foreach_loop":{"id":4},"walk_if":{"id":2},"walk_loop":{"id":4},'
        .'"walk_recursive_if":{"id":2},"walk_recursive_loop":{"id":4},"plus_inline_if":{"id":1,"e":1},'
        .'"merge_other_if":{"e":1,"m":1},"plus_other_if":{"e":1,"p":1},"param_merge_if":{"id":1,"m":1,"x":1},'
        .'"param_walk_if":{"id":2,"x":1}}',
    );
});

it('keeps a resource\'s local keys required past a merge in an `if` and a loop', function () {
    $post = new Post(['title' => 't']);
    $post->id = 3;

    expect(publishedResourceKeyTypes(MergedPostResource::class, Post::class))->toBe(['id' => 'number', 'title' => 'string'])
        ->and((new MergedPostResource($post))->resolve(request()))->toBe(['id' => 3, 'title' => 't', 'a' => true, 'b' => true])
        ->and((new MergedPostResource($post))->resolve(Request::create('/', 'GET', ['full' => 1, 'more' => 1])))
        ->toBe(['id' => 3, 'title' => 't', 'body' => 'b', 'more' => 1, 'a' => true, 'b' => true]);
});

// The walk does not follow a value rewritten in place, at the top level or in a block, so a seed would keep the
// argument's old value types. Its keys go unpublished instead, the known gap; Arr::set() opens the shape for its key.
it('seeds nothing past a rewrite of the values in place, at the top level or in a block', function () {
    expect(analyzedKeyTypes(InPlaceRewrittenParameter::class))->toBe([
        'walked' => '{ x: number }',
        'walked_recursive' => '{ x: number }',
        'ref_looped' => '{ x: number }',
        'arr_set' => '{ x: number; [key: string]: unknown }',
        'if_walked' => '{ x: number }',
        'if_arr_set' => '{ x: number; [key: string]: unknown }',
        'loop_walked' => '{ x: number }',
        'try_walked' => '{ x: number }',
    ])->and(json_encode(new InPlaceRewrittenParameter()->toArray()))->toBe(
        '{"walked":{"id":"1","n":"2","x":1},"walked_recursive":{"id":"1","n":"2","x":1},'
        .'"ref_looped":{"id":"1","n":"2","x":1},"arr_set":{"id":"str","n":2,"x":1},"if_walked":{"id":1,"x":1},'
        .'"if_arr_set":{"id":1,"x":1},"loop_walked":{"id":1,"x":1},"try_walked":{"id":"1","x":1}}',
    )->and(json_encode(array_slice(new InPlaceRewrittenParameter(true, [1])->toArray(), 4)))->toBe(
        '{"if_walked":{"id":"1","x":1},"if_arr_set":{"id":"str","x":1},"loop_walked":{"id":"1","x":1},'
        .'"try_walked":{"id":"1","x":1}}',
    );
});

it('seeds nothing past a top-level rewrite in place in a helper a resource reads or spreads', function () {
    expect(analyzedKeyTypes(InPlaceSpreadResource::class))->toBe(['ref_stringified' => '{ x: number }', 'label' => 'string'])
        ->and((new InPlaceSpreadResource(null))->resolve(request()))
        ->toBe(['ref_stringified' => ['id' => '1', 'n' => '2', 'x' => 1], 'count' => '#1', 'label' => 'l']);
});

it('seeds nothing past an array_splice(), whose keys publish open', function () {
    expect(analyzedKeyTypes(ReplacedParameter::class, 'values')['spliced'])->toBe('{ x: number; [key: string]: unknown }')
        ->and(json_encode(new ReplacedParameter()->values()['spliced']))->toBe('{"0":5,"id":1,"x":1}');
});

// The unset() that may not run leaves `id` optional, as an unset() of `id` alone in a block would.
it('keeps the seed past a key-keeping sort, an unset() of one key, an unset() that may not run, a ??= and an array_push()', function () {
    $cleared = new ReplacedParameter;
    $cleared->clear = true;

    expect(analyzedKeyTypes(ReplacedParameter::class, 'kept'))->toBe([
        'ksorted' => '{ id: number; x: number }',
        'trimmed' => '{ id: number; x: number }',
        'maybeCleared' => '{ id?: number; x: number }',
        'coalesced' => '{ id: number; x: number }',
        'pushed' => '{ id: number; x: number; [key: string]: unknown }',
    ])->and(json_encode(new ReplacedParameter()->kept()))->toBe(
        '{"ksorted":{"id":1,"x":1},"trimmed":{"id":1,"x":1},"maybeCleared":{"id":1,"x":1},"coalesced":{"id":1,"x":1},'
        .'"pushed":{"id":1,"0":5,"x":1}}',
    )->and(json_encode($cleared->kept()['maybeCleared']))->toBe('{"x":1}');
});

// An unset() takes every key and brings none; each other write may bring keys the walk never names. A `do` loop's
// condition runs after its body.
it('makes the keys held before a whole rewrite in a block optional, and opens the shape where it brings keys', function () {
    $rewritten = new MaybeRewrittenParameter;
    $rewritten->rewrite = true;

    expect(analyzedKeyTypes(MaybeRewrittenParameter::class))->toBe([
        'unset' => '{ id?: number; x: number }',
        'sorted' => '{ id?: number; x: number; [key: string]: unknown }',
        'looped' => '{ id?: number; x: number; [key: string]: unknown }',
        'destructured' => '{ id?: number; x: number; [key: string]: unknown }',
        'matched' => '{ id?: number; x: number; [key: string]: unknown }',
        'filtered' => '{ id?: number; name?: string; url: string; [key: string]: unknown }',
        'excepted' => '{ id?: number; name?: string; x: number; [key: string]: unknown }',
        'local' => '{ id?: number; x: number }',
        'resorted' => '{ id?: number; k?: number; x: number; [key: string]: unknown }',
        'signed' => '{ id?: number; [key: `k_${string}`]: number | undefined; x: number; [key: string]: unknown }',
    ])->and(json_encode(new MaybeRewrittenParameter()->toArray()))->toBe(
        '{"unset":{"id":1,"x":1},"sorted":{"id":1,"x":1},"looped":{"id":1,"x":1},"destructured":{"id":1,"x":1},'
        .'"matched":{"id":1,"x":1},"filtered":{"id":0,"name":"n","url":"\/x"},"excepted":{"id":1,"name":"n","x":1},'
        .'"local":{"id":1,"x":1},"resorted":{"id":1,"k":2,"x":1},"signed":{"id":1,"k_a":1,"x":1}}',
    )->and(json_encode($rewritten->toArray()))->toBe(
        '{"unset":{"x":1},"sorted":{"0":1,"x":1},"looped":{"k":1,"x":1},"destructured":{"k":1,"x":1},'
        .'"matched":{"0":"a","k":"a","1":"a","x":1},"filtered":{"name":"n","url":"\/x"},"excepted":{"name":"n","x":1},'
        .'"local":{"x":1},"resorted":{"0":1,"1":1,"x":1},"signed":{"0":1,"1":1,"x":1}}',
    );
});

it('opens the seeded shape past an array_push() inside another statement', function () {
    expect(analyzedKeyTypes(ReplacedParameter::class, 'pushedInline'))->toBe([
        'counted' => '{ id: number; x: number; [key: string]: unknown }',
    ])->and(json_encode(new ReplacedParameter()->pushedInline()['counted']))->toBe('{"id":1,"0":5,"x":1}');
});

it('keeps the seed past a ??= in the method\'s own return', function () {
    expect(analyzedKeyTypes(ReplacedParameter::class, 'coalescedOwn'))->toBe(['id' => 'number', 'x' => 'number'])
        ->and(json_encode(new ReplacedParameter()->coalescedOwn()))->toBe('{"id":1,"x":1}');
});

it('seeds a map callback\'s parameter from its element', function () {
    expect(analyzedKeyTypes(SeededRows::class)['rows'])->toBe('{ id: number; seen: boolean }[]')
        ->and(json_encode(new SeededRows()->toArray()))->toBe('{"rows":[{"id":1,"seen":true}]}');
});

it('keeps two same-named enums apart through a seed', function () {
    expect(analyzedKeyTypes(SeededEnumMembers::class, 'pair'))->toBe([
        'a' => 'WorkbenchStatusType', 'b' => 'CrmStatusType', 'stamped' => 'boolean',
    ])->and(analyzedImportNames(SeededEnumMembers::class, 'pair'))
        ->toBe(['StatusType as CrmStatusType', 'StatusType as WorkbenchStatusType'])
        ->and(json_encode(new SeededEnumMembers()->pair()))->toBe('{"a":0,"b":"lead","stamped":true}');
});

// ObjectMembers cannot hand a same-named pair out member by member once one sits a level down.
it('keeps only the written keys where the seed cannot place a same-named pair', function () {
    expect(analyzedKeyTypes(SeededEnumMembers::class, 'nestedPair'))->toBe(['stamped' => 'boolean'])
        ->and(json_encode(new SeededEnumMembers()->nestedPair()))->toBe('{"a":0,"inner":{"b":"lead"},"stamped":true}');
});

it('keeps the open signature where an import-less read would type a seeded class unknown', function () {
    expect(analyzedKeyTypes(SeededEnumMembers::class, 'values'))->toBe([
        'pair' => '{ stamped: boolean; [key: string]: unknown }',
    ])->and(json_encode(new SeededEnumMembers()->values()))->toBe('{"pair":{"a":0,"b":"lead","stamped":true}}');
});

it('keeps only the written keys where the argument holds an EnumResource wrap', function () {
    expect(analyzedKeyTypes(SeededEnumMembers::class, 'seededWrapped'))->toBe(['stamped' => 'boolean']);
});

it('seeds a wrapped enum with Tolki off as the argument prints alone', function () {
    config()->set('ts-publish.enums.use_tolki_package', false);

    expect(analyzedKeyTypes(SeededEnumMembers::class, 'wrapped'))->toBe(['status' => 'StatusType'])
        ->and(analyzedKeyTypes(SeededEnumMembers::class, 'seededWrapped'))
        ->toBe(['status' => 'StatusType', 'stamped' => 'boolean'])
        ->and(analyzedImportNames(SeededEnumMembers::class, 'seededWrapped'))
        ->toBe(analyzedImportNames(SeededEnumMembers::class, 'wrapped'));
});

it('reads a parent:: callee an inherited method passes its array to as the declaring class\'s parent', function () {
    expect(analyzedKeyTypes(DecoratingChild::class))->toBe(['v' => '{ id: number; grand: number }'])
        ->and(json_encode(new DecoratingChild()->toArray()))->toBe('{"v":{"id":1,"grand":1}}');
});
