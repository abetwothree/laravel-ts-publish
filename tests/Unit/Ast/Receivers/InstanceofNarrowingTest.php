<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Receivers\InstanceofNarrowing;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

describe('classes', function () {
    test('narrows each class by the first case of the lattice that matches, in any order of tests', function (array $classes, array $tested, array $narrowed) {
        expect(InstanceofNarrowing::classes($classes, $tested))->toBe($narrowed);
    })->with([
        'a class that is a tested class stays' => [[Post::class], [Model::class], [Post::class]],
        'an unrelated tested interface keeps the class' => [[Post::class], [Authenticatable::class], [Post::class]],
        'a tested subclass replaces a morph bound' => [[Model::class], [Post::class, User::class], [Post::class, User::class]],
        'a tested class replaces an interface' => [[Arrayable::class], [Post::class], [Post::class]],
        'a class no tested class can be is dropped' => [[User::class, Post::class], [Post::class], [Post::class]],
        'no class can pass' => [[User::class], [Post::class], []],
    ]);
});

describe('proven', function () {
    test('holds what both proofs leave, or the tested classes where none of the held can pass', function (array $held, array $tested, array $proven) {
        expect(InstanceofNarrowing::proven($held, $tested))->toBe($proven);
    })->with([
        'a held class the test keeps' => [[Post::class, User::class], [Post::class], [Post::class]],
        'a held class no tested class can be' => [[User::class], [Post::class], [Post::class]],
    ]);
});
