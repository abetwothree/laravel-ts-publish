<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EntryIntersectionExtendsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\LabeledHolderResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AbstractEntry;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\HasLabel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\LabeledEntry;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\LabeledPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\LabelingPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\HeldStats;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\LabeledHolder;
use Illuminate\Contracts\Support\Arrayable;
use PhpParser\Node\Expr;
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
