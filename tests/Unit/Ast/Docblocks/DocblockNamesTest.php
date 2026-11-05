<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\DocblockNames;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Commentable;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\LinkingPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\LinksComments;
use Workbench\App\Models\Comment;

test('a name resolves through its file\'s imports, then its namespace, for every class-like kind', function (string $name, string $expected) {
    expect(DocblockNames::resolve($name, new ReflectionClass(LinkingPost::class)))->toBe($expected);
})->with([
    'a trait of its namespace' => ['LinksComments', LinksComments::class],
    'an interface of its namespace' => ['Commentable', Commentable::class],
    'an imported class' => ['Comment', Comment::class],
    'a nullable imported class' => ['?Comment', '?'.Comment::class],
    'a fully qualified name' => ['\\'.Comment::class, Comment::class],
    'a name nothing answers to, as written' => ['NoSuchThing', 'NoSuchThing'],
]);
