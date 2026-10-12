<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\CallSite;
use AbeTwoThree\LaravelTsPublish\Ast\ParameterBinder;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HelperParameterKindsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PassedArgumentEdgesResource;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use Workbench\App\Http\Resources\PassedArgumentHelpersResource;
use Workbench\App\Models\Post;

/**
 * Each property a class's analysis publishes, as `name => type`, `?` marking an optional one.
 *
 * @param  class-string  $class
 * @return array<string, string>
 */
function passedArgumentTypes(string $class, string $method = 'toArray'): array
{
    $types = [];

    foreach (resolve(AstEngine::class)->analyze($class, $method, Post::class)->properties as $property) {
        $types[$property['name']] = ($property['optional'] ? '?' : '').$property['type'];
    }

    return $types;
}

test('a helper read as a value reads an untyped parameter as what its call passes', function () {
    expect(passedArgumentTypes(PassedArgumentHelpersResource::class))->toBe([
        'row' => '{ pk: number; title: string }',
        'author_card' => '{ name: string; email: string }',
        'static_row' => '{ static_pk: number }',
        'outer' => '{ inner: { pk: number; title: string } }',
        'loaded_card' => '?{ name: string; email: string }',
        'loaded_count' => '?{ count: number }',
        'has_title' => '?{ value: string }',
        'transformed' => '?{ value: string }',
    ]);
});

// A written parameter keeps its class binding up to the write, then its local answers: Laravel sends the author's name.
test('each call publishes its own argument, a written parameter its local, and an argument nothing types or a scalar body stay unread', function () {
    expect(passedArgumentTypes(PassedArgumentEdgesResource::class))->toBe([
        'post_label' => '{ title: string; name: unknown }',
        'author_label' => '{ title: unknown; name: string }',
        'unknown_label' => '{ title: unknown; name: unknown }',
        'nested' => '{ deeper: unknown[] }',
        'written' => '{ author_name: string }',
        'shout' => '?unknown',
        'fallback' => '{ value: unknown }',
        'fallback_null' => '{ value: string }',
    ]);
});

// A model no row backs yet keys as `| null`, so the binding a call passes keeps that mark into the helper's body.
test('a helper passed a model no row backs yet reads its key as possibly null', function () {
    expect(passedArgumentTypes(PassedArgumentEdgesResource::class, 'unsaved'))->toBe([
        'unsaved_row' => '{ k: number | null }',
        'k' => 'number | null',
    ]);
});

test('the signature is empty for a call that binds nothing, and one for two arguments that bind alike', function () {
    $binder = resolve(ParameterBinder::class);
    $held = $binder->passedBinding(['type' => 'unknown', 'optional' => false], ReceiverType::of(Post::class));
    $typed = $binder->passedBinding(['type' => 'Post', 'optional' => true, 'modelFqcn' => Post::class], ReceiverType::of(Post::class));
    $nullable = $binder->passedBinding(['type' => 'Post | null', 'optional' => false, 'modelFqcn' => Post::class], ReceiverType::of(Post::class));

    expect(CallSite::signature([]))->toBe('')
        ->and($binder->passedBinding(['type' => 'unknown', 'optional' => false], null))->toBeNull()
        ->and($held)->toBe(['model' => Post::class, 'classes' => null, 'value' => null, 'unsaved' => false])
        ->and(CallSite::signature(['post' => $held]))->toBe(CallSite::signature(['post' => $typed]))
        ->and(CallSite::signature(['post' => $held]))->not->toBe(CallSite::signature(['post' => $nullable]));
});

test('a call binds nothing in a method whose parameters all declare a type, and resolves none of its arguments', function () {
    $scope = new AnalysisScope(new ReflectionClass(HelperParameterKindsResource::class), Post::class);
    $call = new MethodCall(new Variable('this'), 'scalar', [new Arg(new String_('5'))]);

    expect(new CallSite($call, $scope, chainHandlersThrowingEngine())->passedTo(new ReflectionMethod(HelperParameterKindsResource::class, 'scalar')))
        ->toBe([]);
});
