<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Receivers\DeclaredClasses;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Commentable;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\LinksComments;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ScopedAuthorPost;
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
