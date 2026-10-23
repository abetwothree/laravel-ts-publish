<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\TypedClosureParameterResource;
use PhpParser\Node\Stmt\Expression;
use Workbench\App\Models\Post;

it('binds a closure parameter to the class its native type names', function () {
    expect(publishedResourceKeyTypes(TypedClosureParameterResource::class, Post::class))->toBe([
        'codes' => '{ id: string }[]',
        'opaque_codes' => '{ id: string }[]',
        'spread_comments' => "(Omit<Comment, 'flag'> & { flag: boolean })[]",
        'comment_authors' => '(string | null)[]',
        'options' => '{ id: number; d: number }[]',
        'nullable_options' => '{ id: number }[]',
        'replaced_options' => '{ id: string }[]',
        'null_default?' => 'null',
        'null_default_member?' => 'unknown',
        'enum_default?' => 'number',
        'unit_enum_default?' => 'string',
    ]);
});

// A writer's value binding that names a class, or is only null, says more than the class the parameter declares.
it('binds no declared class over a default that names an enum case or is null', function () {
    $sent = new TypedClosureParameterResource(new Post)->resolve(request());

    expect(publishedResourceKeyTypes(TypedClosureParameterResource::class, Post::class))->toMatchArray([
        'null_default?' => 'null',
        'null_default_member?' => 'unknown',
        'enum_default?' => 'number',
        'unit_enum_default?' => 'string',
    ])->and($sent)->toMatchArray([
        'null_default' => null,
        'null_default_member' => null,
        'enum_default' => 1,
        'unit_enum_default' => 'Published',
    ]);
});

// No writer binds this closure's parameter, so only its declared class can, before the body's write ends it.
it('ends a typed parameter\'s binding at a write that replaces it with another class', function () {
    $statement = new AstParser()->parseSource(<<<'PHP'
        <?php
        function (\AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\MapOption $option) {
            $kept = $option->itemId;
            $option = new \AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\DockCode('a');

            return ['kept' => $kept, 'code' => $option->code];
        };
        PHP)[0];
    $scope = new AnalysisScope(new ReflectionClass(TypedClosureParameterResource::class), Post::class);
    $engine = new ResourceAstAnalyzer(new ReflectionClass(TypedClosureParameterResource::class), Post::class, 'toArray', null, $scope);

    expect($statement)->toBeInstanceOf(Expression::class)
        ->and($engine->resolve($statement->expr)['type'])->toBe('{ kept: number; code: string }');
});
