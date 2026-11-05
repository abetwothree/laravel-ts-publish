<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Resources\ProxyDeclaration;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ArchivingPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\DraftPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EntryIntersectionExtendsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EntryIntersectionNullableVarResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EntryIntersectionVarResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ExplicitLabelingPostOverIntersectionResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ExplicitPostOverHeldStatsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ExplicitPostOverIntersectionResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HeldStatsChildResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HeldStatsExtendsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HeldStatsMixinResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HeldStatsOrPostExtendsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HeldStatsPropertyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HeldStatsVarResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\LabelingPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ListingPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ModelWrappedPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\NamesakeWrappedResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\NullablePostExtendsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PostIntersectionExtendsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ProxyCollectedPostCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ProxyMediaOrPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ScopedPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AbstractEntry;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\HasLabel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\NamesakeInvoice;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\HeldStats;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\NamesakeStatusCard;
use Illuminate\Contracts\Support\Arrayable;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;

test('what $resource is declared to hold comes from @var, @extends, @mixin, @property, then an ancestor\'s tags', function (string $resource, ?array $expected) {
    expect(ProxyDeclaration::of(new ReflectionClass($resource)))->toBe($expected);
})->with([
    '@var' => [HeldStatsVarResource::class, ['classes' => [HeldStats::class], 'named' => true, 'nullable' => false, 'intersections' => [], 'source' => 'var']],
    '@extends' => [HeldStatsExtendsResource::class, ['classes' => [HeldStats::class], 'named' => true, 'nullable' => false, 'intersections' => [], 'source' => 'extends']],
    '@mixin' => [HeldStatsMixinResource::class, ['classes' => [HeldStats::class], 'named' => true, 'nullable' => false, 'intersections' => [], 'source' => 'mixin']],
    '@property' => [HeldStatsPropertyResource::class, ['classes' => [HeldStats::class], 'named' => true, 'nullable' => false, 'intersections' => [], 'source' => 'property']],
    'an ancestor\'s @extends' => [HeldStatsChildResource::class, ['classes' => [HeldStats::class], 'named' => true, 'nullable' => false, 'intersections' => [], 'source' => 'extends']],
    'a null arm' => [NullablePostExtendsResource::class, ['classes' => [Post::class], 'named' => true, 'nullable' => true, 'intersections' => [], 'source' => 'extends']],
    '@var before a tag' => [NamesakeWrappedResource::class, ['classes' => [NamesakeStatusCard::class], 'named' => true, 'nullable' => false, 'intersections' => [], 'source' => 'var']],
    'a model mixin' => [PostResource::class, ['classes' => [Post::class], 'named' => true, 'nullable' => false, 'intersections' => [], 'source' => 'mixin']],
    'a missing IDE-helper class' => [DraftPostResource::class, null],
    'the Eloquent alias' => [ListingPostResource::class, null],
    'Model itself' => [ScopedPostResource::class, null],
    'an interface a model implements' => [ArchivingPostResource::class, ['classes' => [Arrayable::class], 'named' => true, 'nullable' => false, 'intersections' => [], 'source' => 'mixin']],
    'a tag naming a class #[TsResource(model:)] is not' => [ExplicitPostOverHeldStatsResource::class, null],
    'an intersection' => [EntryIntersectionExtendsResource::class, ['classes' => [AbstractEntry::class], 'named' => true, 'nullable' => false, 'intersections' => [AbstractEntry::class => [HasLabel::class]], 'source' => 'extends']],
    'a DNF @var' => [EntryIntersectionNullableVarResource::class, ['classes' => [AbstractEntry::class], 'named' => true, 'nullable' => true, 'intersections' => [AbstractEntry::class => [HasLabel::class]], 'source' => 'var']],
    'an intersection of interfaces' => [LabelingPostResource::class, ['classes' => [Arrayable::class], 'named' => true, 'nullable' => false, 'intersections' => [Arrayable::class => [HasLabel::class]], 'source' => 'mixin']],
    'an intersection #[TsResource(model:)] is not every member of' => [ExplicitPostOverIntersectionResource::class, null],
    'an intersection #[TsResource(model:)] is every member of' => [ExplicitLabelingPostOverIntersectionResource::class, ['classes' => [Post::class], 'named' => true, 'nullable' => false, 'intersections' => [Post::class => [HasLabel::class]], 'source' => 'mixin']],
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
    'an intersection\'s model member' => [PostIntersectionExtendsResource::class, Post::class],
    'an intersection of an abstract model and an interface' => [EntryIntersectionVarResource::class, AbstractEntry::class],
    'an intersection of interfaces' => [LabelingPostResource::class, null],
]);

test('a union a tag declares keeps every class it names', function () {
    expect(ProxyDeclaration::of(new ReflectionClass(HeldStatsOrPostExtendsResource::class)))
        ->toBe(['classes' => [HeldStats::class, Post::class], 'named' => true, 'nullable' => false, 'intersections' => [], 'source' => 'extends']);
});

// A collection's constructor swaps in its items, so its tags describe them, never what `$this->resource` holds.
test('a resource collection\'s tags declare nothing it holds', function () {
    expect(ProxyDeclaration::of(new ReflectionClass(ProxyCollectedPostCollection::class)))->toBeNull();
});
