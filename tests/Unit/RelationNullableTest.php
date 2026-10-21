<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\RelationMap;
use AbeTwoThree\LaravelTsPublish\RelationNullable;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Relations\Ancestors;
use Workbench\App\Models\Category;

test('isNullable() reads an entry without its relation class, as Laravel\'s own list gives it, by its short type', function () {
    RelationMap::reset();
    $nullable = new RelationNullable;

    expect($nullable->isNullable(['name' => 'children', 'type' => 'HasMany', 'related' => Category::class]))->toBeFalse()
        ->and($nullable->isNullable(['name' => 'ancestors', 'type' => 'Ancestors', 'related' => Category::class]))->toBeTrue()
        ->and($nullable->isNullable([
            'name' => 'ancestors', 'type' => 'Ancestors', 'related' => Category::class, 'class' => Ancestors::class,
        ]))->toBeFalse();
});
