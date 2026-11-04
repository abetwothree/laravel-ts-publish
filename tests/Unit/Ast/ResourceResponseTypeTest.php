<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Resources\ResourceResponseType;

// A response arm is a resource or paginator type, which prints no arrow; the guard keeps the join right regardless.
test('a response union groups an intersection or an arrow arm beside another arm, and leaves a lone arm as it is', function (array $types, string $union) {
    $join = (new ReflectionMethod(ResourceResponseType::class, 'union'))->getClosure(new ResourceResponseType);

    expect($join($types))->toBe($union);
})->with([
    'an intersection beside an arm' => [['A & B', 'C'], '(A & B) | C'],
    'an arrow returning an intersection beside an arm' => [['() => A & B', 'C'], '(() => A & B) | C'],
    'an arrow returning a union beside an arm' => [['() => A | B', 'C'], '(() => A | B) | C'],
    'a lone arrow' => [['() => A & B'], '() => A & B'],
]);
