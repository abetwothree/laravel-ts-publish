<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Resources\ProxyDeclaration;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ArchivingPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\DraftPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ExplicitPostOverHeldStatsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HeldStatsChildResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HeldStatsExtendsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HeldStatsMixinResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HeldStatsOrPostExtendsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HeldStatsPropertyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HeldStatsVarResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ListingPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ModelWrappedPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\NamesakeWrappedResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\NullablePostExtendsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ProxyCollectedPostCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ProxyMediaOrPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ScopedPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\NamesakeInvoice;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\HeldStats;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\NamesakeStatusCard;
use Illuminate\Contracts\Support\Arrayable;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;

test('what $resource is declared to hold comes from @var, @extends, @mixin, @property, then an ancestor\'s tags', function (string $resource, ?array $expected) {
    expect(ProxyDeclaration::of(new ReflectionClass($resource)))->toBe($expected);
})->with([
    '@var' => [HeldStatsVarResource::class, ['classes' => [HeldStats::class], 'named' => true, 'nullable' => false, 'source' => 'var']],
    '@extends' => [HeldStatsExtendsResource::class, ['classes' => [HeldStats::class], 'named' => true, 'nullable' => false, 'source' => 'extends']],
    '@mixin' => [HeldStatsMixinResource::class, ['classes' => [HeldStats::class], 'named' => true, 'nullable' => false, 'source' => 'mixin']],
    '@property' => [HeldStatsPropertyResource::class, ['classes' => [HeldStats::class], 'named' => true, 'nullable' => false, 'source' => 'property']],
    'an ancestor\'s @extends' => [HeldStatsChildResource::class, ['classes' => [HeldStats::class], 'named' => true, 'nullable' => false, 'source' => 'extends']],
    'a null arm' => [NullablePostExtendsResource::class, ['classes' => [Post::class], 'named' => true, 'nullable' => true, 'source' => 'extends']],
    '@var before a tag' => [NamesakeWrappedResource::class, ['classes' => [NamesakeStatusCard::class], 'named' => true, 'nullable' => false, 'source' => 'var']],
    'a model mixin' => [PostResource::class, ['classes' => [Post::class], 'named' => true, 'nullable' => false, 'source' => 'mixin']],
    'a missing IDE-helper class' => [DraftPostResource::class, null],
    'the Eloquent alias' => [ListingPostResource::class, null],
    'Model itself' => [ScopedPostResource::class, null],
    'an interface a model implements' => [ArchivingPostResource::class, ['classes' => [Arrayable::class], 'named' => true, 'nullable' => false, 'source' => 'mixin']],
    'a tag naming a class #[TsResource(model:)] is not' => [ExplicitPostOverHeldStatsResource::class, null],
]);

test('the model the declarations name is a tag\'s model arm, else the @var\'s, a null arm allowed', function (string $resource, ?string $expected) {
    expect(ProxyDeclaration::model(new ReflectionClass($resource)))->toBe($expected);
})->with([
    'a null arm' => [NullablePostExtendsResource::class, Post::class],
    'a union with a class other than a model' => [HeldStatsOrPostExtendsResource::class, Post::class],
    'a tag over a @var naming no model' => [NamesakeWrappedResource::class, NamesakeInvoice::class],
    'a @var union of an enum and a model' => [ProxyMediaOrPostResource::class, Post::class],
    'a subclass\'s mixin over its parent\'s @var Model' => [ModelWrappedPostResource::class, Post::class],
    'a class other than a model' => [HeldStatsExtendsResource::class, null],
    'a mixin naming nothing loadable' => [DraftPostResource::class, null],
    'an interface mixin' => [ArchivingPostResource::class, null],
    '#[TsResource(model:)] over a value-object mixin' => [ExplicitPostOverHeldStatsResource::class, Post::class],
]);

test('a union a tag declares keeps every class it names', function () {
    expect(ProxyDeclaration::of(new ReflectionClass(HeldStatsOrPostExtendsResource::class)))
        ->toBe(['classes' => [HeldStats::class, Post::class], 'named' => true, 'nullable' => false, 'source' => 'extends']);
});

// A collection's constructor swaps in its items, so its tags describe them, never what `$this->resource` holds.
test('a resource collection\'s tags declare nothing it holds', function () {
    expect(ProxyDeclaration::of(new ReflectionClass(ProxyCollectedPostCollection::class)))->toBeNull();
});
