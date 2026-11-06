<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Receivers\DeclaredClasses;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AbstractEntry;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Commentable;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\HasLabel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\LabelingPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\LinksComments;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ScopedAuthorPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\DocumentedFormatDate;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\LabeledHolder;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;

test('a class-like is a class, an interface, a trait or an enum', function (string $fqcn, bool $expected) {
    expect(DeclaredClasses::isClassLike($fqcn))->toBe($expected);
})->with([
    'a class' => [Comment::class, true],
    'an interface' => [Commentable::class, true],
    'a trait' => [LinksComments::class, true],
    'an enum' => [Status::class, true],
    'no such name' => ['NoSuchThing', false],
]);

test('a value class is a class, an interface or an enum, never a trait', function (string $fqcn, bool $expected) {
    expect(DeclaredClasses::isValueClass($fqcn))->toBe($expected);
})->with([
    'a class' => [Comment::class, true],
    'an interface' => [Commentable::class, true],
    'a trait' => [LinksComments::class, false],
    'an enum' => [Status::class, true],
    'no such name' => ['NoSuchThing', false],
]);

test('a generic type spells its arguments, split at its top-level commas', function (string $type, ?array $expected) {
    expect(DeclaredClasses::genericArguments($type))->toBe($expected);
})->with([
    'one argument' => ['JsonResource<Post>', ['Post']],
    'a union argument' => ['JsonResource<Post|null>', ['Post|null']],
    'two arguments, one generic' => ['\\Attribute<Collection<int, Post>, never>', ['Collection<int, Post>', 'never']],
    'a space before the bracket' => ['JsonResource <Post>', ['Post']],
    'no arguments' => ['JsonResource', null],
    'text after the closing bracket' => ['JsonResource<Post>[]', null],
]);

// Post's file reads a bare `Comment` as its namespace's model.
test('a collection generic spells its kind of key and the concrete model it lists', function (string $type, ?array $expected) {
    expect(DeclaredClasses::collectionGeneric($type, new ReflectionClass(Post::class)))->toBe($expected);
})->with([
    'an int key' => ['Collection<int, Comment>', ['key' => 'int', 'element' => Comment::class]],
    'only the element, nullable' => ['?Collection<Comment>', ['key' => null, 'element' => Comment::class]],
    'a string key beside null' => ['Collection<string, Comment>|null', ['key' => 'string', 'element' => Comment::class]],
    'either kind as a union' => ['Collection<int | string, Comment>', ['key' => 'array-key', 'element' => Comment::class]],
    'an element that is no concrete model' => ['Collection<string, int>', ['key' => 'string', 'element' => null]],
    'a nested element' => ['Collection<int, Collection<int, Comment>>', null],
    'a key no collection takes' => ['Collection<float, Comment>', null],
    'two collections' => ['Collection<int, Comment>|Collection<int, Post>', null],
    'no generic' => ['Collection', null],
    'a list after the bracket' => ['Collection<int, Comment>[]', null],
]);

test('a native type names one class, static read as the receiver and self as the declarer', function (?ReflectionType $type, ?string $expected) {
    expect(DeclaredClasses::singleClass($type, Post::class, Comment::class))->toBe($expected);
})->with([
    'a class' => [fn () => new ReflectionFunction(fn (): Post => new Post)->getReturnType(), Post::class],
    'a nullable class' => [fn () => new ReflectionFunction(fn (): ?Comment => null)->getReturnType(), Comment::class],
    'static' => [fn () => new ReflectionMethod(ScopedAuthorPost::class, 'hideAuthor')->getReturnType(), Post::class],
    'a builtin' => [fn () => new ReflectionFunction(fn (): int => 1)->getReturnType(), null],
    'a union' => [fn () => new ReflectionFunction(fn (): Post|Comment => new Post)->getReturnType(), null],
    'none' => [null, null],
]);

// LabeledHolder's file imports AbstractEntry, HasLabel and Post.
test('a docblock intersection names its primary member, the first that is no interface, and its other members', function (string $type, array $expected) {
    $context = new ReflectionClass(LabeledHolder::class);

    expect(DeclaredClasses::docblockArms($type, $context, LabeledHolder::class, LabeledHolder::class))->toBe($expected);
})->with([
    'a model and an interface' => ['AbstractEntry&HasLabel', [
        'classes' => [AbstractEntry::class], 'named' => true, 'nullable' => false, 'intersections' => [AbstractEntry::class => [HasLabel::class]],
    ]],
    'the interface first' => ['HasLabel&Post', [
        'classes' => [Post::class], 'named' => true, 'nullable' => false, 'intersections' => [Post::class => [HasLabel::class]],
    ]],
    'only interfaces' => ['HasLabel&\\Countable', [
        'classes' => [HasLabel::class], 'named' => true, 'nullable' => false, 'intersections' => [HasLabel::class => [Countable::class]],
    ]],
    'a DNF arm beside null' => ['(AbstractEntry&HasLabel)|null', [
        'classes' => [AbstractEntry::class], 'named' => true, 'nullable' => true, 'intersections' => [AbstractEntry::class => [HasLabel::class]],
    ]],
    'a DNF arm beside a class' => ['(AbstractEntry&HasLabel)|Post', [
        'classes' => [AbstractEntry::class, Post::class], 'named' => true, 'nullable' => false, 'intersections' => [AbstractEntry::class => [HasLabel::class]],
    ]],
    'a member the primary already is' => ['\\'.LabelingPost::class.'&HasLabel', [
        'classes' => [LabelingPost::class], 'named' => true, 'nullable' => false, 'intersections' => [],
    ]],
    'arms sharing a primary keep the members every one names' => ['(Post&HasLabel)|Post', [
        'classes' => [Post::class], 'named' => true, 'nullable' => false, 'intersections' => [],
    ]],
    'a member no class loads' => ['Post&NoSuchContract', ['classes' => [], 'named' => false, 'nullable' => false, 'intersections' => []]],
    'a builtin member' => ['Post&string', ['classes' => [], 'named' => false, 'nullable' => false, 'intersections' => []]],
    'a group holding two intersections' => ['(Post)&(HasLabel)', ['classes' => [], 'named' => false, 'nullable' => false, 'intersections' => []]],
]);

test('a native intersection or DNF type names each arm\'s primary member, and its other members', function (string $property) {
    $type = new ReflectionProperty(LabeledHolder::class, $property)->getType();
    $primary = $property === 'post' ? Post::class : AbstractEntry::class;

    expect(DeclaredClasses::nativeType($type, LabeledHolder::class, LabeledHolder::class))
        ->toBe(['classes' => [$primary], 'intersections' => [$primary => [HasLabel::class]]])
        ->and(DeclaredClasses::nativeClasses($type, LabeledHolder::class, LabeledHolder::class))->toBe([$primary]);
})->with(['an intersection' => ['post'], 'a DNF type beside null' => ['entry']]);

test('a reflected return is the native type, else the tentative one PHP declares on its own method', function (Closure $function, ?string $expected) {
    expect(DeclaredClasses::reflectedReturnType($function())?->__toString())->toBe($expected);
})->with([
    'an internal method\'s tentative type' => [fn () => new ReflectionMethod(DateTimeImmutable::class, 'format'), 'string'],
    'a nullable tentative union' => [fn () => new ReflectionMethod(DateTimeImmutable::class, 'getTimezone'), 'DateTimeZone|false'],
    'an internal method\'s native type' => [fn () => new ReflectionMethod(Exception::class, 'getMessage'), 'string'],
    'an override that drops the tentative type' => [fn () => new ReflectionMethod(DocumentedFormatDate::class, 'format'), null],
    'a typed closure' => [fn () => new ReflectionFunction(fn (): int => 1), 'int'],
    'an untyped closure' => [fn () => new ReflectionFunction(fn () => 1), null],
]);

test('an internal method\'s tentative return names its classes, and its static return', function () {
    expect(DeclaredClasses::returnClasses(DateTimeImmutable::class, 'diff'))->toBe([DateInterval::class])
        ->and(DeclaredClasses::returnClasses(DateTimeImmutable::class, 'getTimezone'))->toBeNull()
        ->and(DeclaredClasses::returnClasses(DocumentedFormatDate::class, 'diff'))->toBe([DateInterval::class])
        ->and(DeclaredClasses::returnsStatic(DateTimeImmutable::class, 'createFromTimestamp'))->toBeTrue();
});
