<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EntryIntersectionExtendsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\GuardedCallbacksResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ItemReadingPostCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\LabeledHolderResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ListingShelfReadsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AbstractEntry;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\HasLabel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\LabeledEntry;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\LabeledPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\LabelingPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\HeldStats;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\LabeledHolder;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use PhpParser\Node\Expr;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;

/** The expression one PHP statement holds. */
function receiverTypeExpr(string $php): Expr
{
    return new AstParser()->parseSource('<?php '.$php.';')[0]->expr;
}

test('a member is read off the class itself, else off the first other member of its intersection declaring it', function (ReceiverType $type, string $class, string $member, bool $isMethod, string $owner) {
    expect($type->memberOwner($class, $member, $isMethod))->toBe($owner);
})->with([
    'a method the class declares' => [fn () => new ReceiverType([AbstractEntry::class], intersections: [AbstractEntry::class => [HasLabel::class]]), AbstractEntry::class, 'getKey', true, AbstractEntry::class],
    'a method only another member declares' => [fn () => new ReceiverType([AbstractEntry::class], intersections: [AbstractEntry::class => [HasLabel::class]]), AbstractEntry::class, 'label', true, HasLabel::class],
    'a method no member declares' => [fn () => new ReceiverType([AbstractEntry::class], intersections: [AbstractEntry::class => [HasLabel::class]]), AbstractEntry::class, 'nothing', true, AbstractEntry::class],
    'a class in no intersection' => [fn () => ReceiverType::of(Post::class), Post::class, 'label', true, Post::class],
    'a property the class declares' => [fn () => new ReceiverType([HeldStats::class], intersections: [HeldStats::class => [LabeledHolder::class]]), HeldStats::class, 'total', false, HeldStats::class],
    'a property only another member declares' => [fn () => new ReceiverType([HeldStats::class], intersections: [HeldStats::class => [LabeledHolder::class]]), HeldStats::class, 'post', false, LabeledHolder::class],
    'a method, never a property of that name' => [fn () => new ReceiverType([HeldStats::class], intersections: [HeldStats::class => [LabeledHolder::class]]), HeldStats::class, 'post', true, HeldStats::class],
]);

test('a copy keeps the intersections, and a narrowing keeps those of the classes it keeps', function () {
    $type = new ReceiverType([AbstractEntry::class], intersections: [AbstractEntry::class => [HasLabel::class]]);
    $kept = [AbstractEntry::class => [HasLabel::class]];

    expect($type->withShortCircuit(true)->intersections)->toBe($kept)
        ->and($type->asUnsaved()->intersections)->toBe($kept)
        ->and($type->asAttributeless()->intersections)->toBe($kept)
        ->and($type->asRuntimeInstance()->intersections)->toBe($kept)
        ->and($type->asDeclaredOnly()->intersections)->toBe($kept)
        ->and($type->withClasses([AbstractEntry::class, Post::class])->intersections)->toBe($kept)
        ->and($type->withClasses([Post::class])->intersections)->toBe([]);
});

// A class gets only the members every declared arm admitting it names, so a bare arm admitting it gives it none.
test('a declared intersection joins each class it admits, less the members the class already is', function (string $class, array $declared, array $expected) {
    expect(ReceiverType::of($class)->withIntersections($declared)->intersections)->toBe($expected);
})->with([
    'the primary member' => [AbstractEntry::class, ['classes' => [AbstractEntry::class], 'intersections' => [AbstractEntry::class => [HasLabel::class]]], [AbstractEntry::class => [HasLabel::class]]],
    'a subtype of it' => [LabeledPost::class, ['classes' => [Post::class], 'intersections' => [Post::class => [HasLabel::class]]], [LabeledPost::class => [HasLabel::class]]],
    'a subtype a bare arm admits too' => [LabeledPost::class, ['classes' => [Post::class, LabeledPost::class], 'intersections' => [Post::class => [HasLabel::class]]], []],
    'a subtype that already is every member' => [LabeledEntry::class, ['classes' => [AbstractEntry::class], 'intersections' => [AbstractEntry::class => [HasLabel::class]]], []],
    'a class only another member of a class primary admits' => [LabelingPost::class, ['classes' => [AbstractEntry::class], 'intersections' => [AbstractEntry::class => [HasLabel::class]]], []],
    'a class one member of only interfaces admits' => [Post::class, ['classes' => [HasLabel::class], 'intersections' => [HasLabel::class => [Arrayable::class]]], [Post::class => [HasLabel::class]]],
    'a class an interface arm and a bare arm admit' => [Post::class, ['classes' => [HasLabel::class, Post::class], 'intersections' => [HasLabel::class => [Arrayable::class]]], []],
    'a class no member names' => [HeldStats::class, ['classes' => [HasLabel::class], 'intersections' => [HasLabel::class => [Arrayable::class]]], []],
]);

test('what $this->resource holds carries the intersection its declaration names', function () {
    $scope = new AnalysisScope(new ReflectionClass(EntryIntersectionExtendsResource::class), AbstractEntry::class);

    expect(resolve(ReceiverClassResolver::class)->resolve(receiverTypeExpr('$this->resource'), $scope)?->intersections)
        ->toBe([AbstractEntry::class => [HasLabel::class]]);
});

test('a property the subject declares as an intersection carries it', function () {
    $scope = new AnalysisScope(new ReflectionClass(LabeledHolder::class), null);

    expect(resolve(ReceiverClassResolver::class)->resolve(receiverTypeExpr('$this->post'), $scope)?->intersections)
        ->toBe([Post::class => [HasLabel::class]]);
});

// A union keeps a class's other members only where every part holding the class names them.
test('a union of receivers keeps an intersection only where every part agrees on it', function (string $php, array $expected) {
    $scope = new AnalysisScope(new ReflectionClass(LabeledHolderResource::class), null);

    expect(resolve(ReceiverClassResolver::class)->resolve(receiverTypeExpr($php), $scope)?->intersections)->toBe($expected);
})->with([
    'both parts an intersection' => ['$flag ? $this->resource->post : $this->resource->post', [Post::class => [HasLabel::class]]],
    'one part the bare class' => ['$flag ? $this->resource->post : new \\Workbench\\App\\Models\\Post', []],
]);

test('a copy keeps the guard', function () {
    $type = ReceiverType::of(Post::class)->asGuarded();

    expect($type->guarded)->toBeTrue()
        ->and(ReceiverType::of(Post::class)->guarded)->toBeFalse()
        ->and($type->asGuarded())->toBe($type)
        ->and($type->withShortCircuit(true)->guarded)->toBeTrue()
        ->and($type->asUnsaved()->guarded)->toBeTrue()
        ->and($type->asAttributeless()->guarded)->toBeTrue()
        ->and($type->asRuntimeInstance()->guarded)->toBeTrue()
        ->and($type->asDeclaredOnly()->guarded)->toBeTrue()
        ->and($type->withClasses([Comment::class])->guarded)->toBeTrue();
});

test('a copy keeps a declared element\'s guard', function () {
    $type = new ReceiverType([Collection::class], elementModel: Comment::class, guardsElement: true);

    expect($type->withShortCircuit(true)->guardsElement)->toBeTrue()
        ->and($type->asGuarded()->guardsElement)->toBeTrue()
        ->and($type->asDeclaredOnly()->guardsElement)->toBeTrue()
        ->and($type->withClasses([EloquentCollection::class])->guardsElement)->toBeTrue()
        ->and(new ReceiverType([Collection::class], elementModel: Comment::class)->guardsElement)->toBeFalse();
});

// The guard starts at `$this->collection` and at what a resource forwards to, and every step and union carries it on.
test('a receiver reached from $this->collection or a forward is guarded down its chain', function (string $subject, string $php, bool $guarded) {
    $scope = new AnalysisScope(new ReflectionClass($subject), null);
    $scope->seedCollectionFacts(new ReflectionClass($subject));

    expect(resolve(ReceiverClassResolver::class)->resolve(receiverTypeExpr($php), $scope)?->guarded)->toBe($guarded);
})->with([
    '$this->collection' => [ItemReadingPostCollection::class, '$this->collection', true],
    'a step keeping its items' => [ItemReadingPostCollection::class, '$this->collection->values()', true],
    'a step replacing them' => [ItemReadingPostCollection::class, '$this->collection->pluck("title")', true],
    'its item' => [ItemReadingPostCollection::class, '$this->collection->first()', true],
    'a union with an unguarded part' => [ItemReadingPostCollection::class, '$flag ? \\Illuminate\\Support\\Collection::make([]) : $this->collection->values()', true],
    'the items $this->resource holds' => [ItemReadingPostCollection::class, '$this->resource', false],
    'a forwarded call' => [ListingShelfReadsResource::class, '\\AbeTwoThree\\LaravelTsPublish\\Tests\\Fixtures\\Http\\Resources\\ListingShelfResource::make($this->resource)->words()', true],
    'a forwarded property' => [ItemReadingPostCollection::class, '$this->resource->first()?->author', true],
    'what a resource holds, read whole' => [ItemReadingPostCollection::class, '$this->resource->first()?->resource', true],
    'a call on the held class itself' => [ListingShelfReadsResource::class, '$this->resource->words()', false],
]);

test('a method declared to list a published model hands that model on as the element', function () {
    $scope = new AnalysisScope(new ReflectionClass(ListingShelfReadsResource::class), null);
    $resolver = resolve(ReceiverClassResolver::class);

    expect($resolver->resolve(receiverTypeExpr('$this->resource->comments()'), $scope)?->elementModel)->toBe(Comment::class)
        ->and($resolver->resolve(receiverTypeExpr('$this->resource->notes()'), $scope)?->elementModel)->toBeNull()
        ->and($resolver->resolve(receiverTypeExpr('$this->resource->abstracts()'), $scope)?->elementModel)->toBeNull()
        ->and($resolver->resolve(receiverTypeExpr('$this->resource->words()'), $scope)?->elementModel)->toBeNull();
});

// A declared list's element is named by its declaration alone: an item it yields is guarded, while a step keeping the
// items hands the element and its guard on, and one replacing them reads as the list's own steps always did.
test('a list a method declares guards the item it yields, never its own steps', function (string $php, bool $guarded, bool $guardsElement) {
    $scope = new AnalysisScope(new ReflectionClass(GuardedCallbacksResource::class), null);
    $type = resolve(ReceiverClassResolver::class)->resolve(receiverTypeExpr($php), $scope);

    expect([$type?->guarded, $type?->guardsElement])->toBe([$guarded, $guardsElement]);
})->with([
    'the list' => ['$this->resource->comments()', false, true],
    'a step keeping its items' => ['$this->resource->comments()->sortBy("id")', false, true],
    'a step adding none' => ['$this->resource->comments()->merge([])', false, true],
    'a union of the list' => ['$flag ? $this->resource->comments() : $this->resource->comments()->values()', false, true],
    'its first item' => ['$this->resource->comments()->first()', true, false],
    'an item found by key' => ['$this->resource->comments()->find(3)', true, false],
    'a query of its items' => ['$this->resource->comments()->toQuery()', true, false],
    'a step replacing its items' => ['$this->resource->comments()->pluck("id")', false, false],
]);

test('a step the engine presumes keeps a declared list\'s items keeps its guard too', function () {
    $scope = new AnalysisScope(new ReflectionClass(GuardedCallbacksResource::class), null);
    $type = resolve(ReceiverClassResolver::class)->resolvePresumingSteps(receiverTypeExpr('$this->resource->comments()->chunk(2)'), $scope);

    expect([$type?->elementModel, $type?->guardsElement])->toBe([Comment::class, true]);
});

test('a list a method declares under string keys is keyed', function (string $php, bool $keyed) {
    $scope = new AnalysisScope(new ReflectionClass(GuardedCallbacksResource::class), null);

    expect(resolve(ReceiverClassResolver::class)->resolve(receiverTypeExpr($php), $scope)?->keyed)->toBe($keyed);
})->with([
    'int keys' => ['$this->resource->comments()', false],
    'string keys' => ['$this->resource->keyedComments()', true],
    'string keys, Eloquent' => ['$this->resource->keyedEloquent()', true],
    'keys of either kind' => ['$this->resource->anyKeyed()', true],
]);
