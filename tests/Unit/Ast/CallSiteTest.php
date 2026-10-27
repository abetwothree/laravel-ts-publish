<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaPageAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallSite;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ParameterBinder;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\InertiaFormatterController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ForwardedArgumentResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HelperParameterKindsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PassedArgumentEdgesResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\VariableHeldModelResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\LabeledPost;
use Illuminate\Database\Eloquent\Model;
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
 * @param  class-string<Model>|null  $model
 * @return array<string, string>
 */
function passedArgumentTypes(string $class, string $method = 'toArray', ?string $model = Post::class): array
{
    $types = [];

    foreach (resolve(AstEngine::class)->analyze($class, $method, $model)->properties as $property) {
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
test('each call publishes its own argument, a written parameter its local, and an argument nothing types stays unread', function () {
    expect(passedArgumentTypes(PassedArgumentEdgesResource::class))->toBe([
        'post_label' => '{ title: string; name: unknown }',
        'author_label' => '{ title: unknown; name: string }',
        'unknown_label' => '{ title: unknown; name: unknown }',
        'nested' => '{ deeper: Record<string, unknown> }',
        'written' => '{ author_name: string }',
        'shout' => '?string',
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

// A declared model binds as a row that exists, which an argument no row backs yet replaces, whichever new model it is.
test('a helper declaring one concrete model reads its key as possibly null only where its call passes a new one', function () {
    expect(passedArgumentTypes(PassedArgumentEdgesResource::class, 'unsavedDeclared'))->toBe([
        'declared_unsaved_row' => '{ k: number | null }',
        'declared_either_unsaved_row' => '{ k: number | null }',
        'declared_saved_row' => '{ k: number }',
    ]);
});

// A model a declared parameter holds reaches the receiver order, so the untyped method's body types the call there too.
test('a helper declaring one concrete model reads an untyped method on it as the resource\'s own call does', function () {
    expect(passedArgumentTypes(VariableHeldModelResource::class, model: LabeledPost::class))->toBe([
        'resource_label' => 'string',
        'param_label' => 'string',
    ]);
});

// A narrower argument is the class the body runs with, so the body reads its members; one nothing types is not.
test('a helper declaring an abstract class or a union reads the narrower class its call passes', function () {
    expect(passedArgumentTypes(PassedArgumentEdgesResource::class, 'declared'))->toBe([
        'model_post' => '{ title: string }',
        'model_unknown' => '{ title: unknown }',
        'by_model' => '?{ model_name: string }',
        'by_union' => '?{ union_name: string }',
    ]);
});

test('a forwarded call reads what it passes an untyped parameter, as a call on $this does', function () {
    expect(passedArgumentTypes(ForwardedArgumentResource::class, 'forPost', null))->toBe([
        'forwarded' => '{ row_title: string }',
        'forwarded_static' => '{ static_title: string }',
        'own_static' => '{ own_title: string }',
    ]);
});

test('a service helper called on a property or another receiver reads what its call passes', function () {
    expect(new InertiaPageAnalyzer()->analyze(['uses' => InertiaFormatterController::class.'@show'])['pageType'] ?? null)
        ->toBe('Inertia.SharedData & { row: { row_title: string } }')
        ->and(passedArgumentTypes(PassedArgumentEdgesResource::class, 'receivers'))
        ->toBe(['summary' => '{ row_title: string }']);
});

// Laravel sends a callable a helper holds as `{}`, and what it returns where the helper calls it: the value read cannot
// tell, so it binds nothing, and warns of nothing.
test('a callable a value read passes binds nothing outside a caller that calls every callable', function () {
    AnalysisWarnings::reset();

    expect(passedArgumentTypes(PassedArgumentEdgesResource::class, 'callables'))->toBe([
        'held_closure' => '{ held: unknown }',
        'held_callable' => '{ held: unknown }',
        'called_closure' => '{ called?: unknown }',
        'called_callable' => '{ called?: unknown }',
    ])
        ->and(AnalysisWarnings::all())->toBe([]);
});

// The spread sends the Closure a helper holds as `{}`, so only a first-class callable, which the engine types as that
// Closure, binds its parameter.
test('a closure a spread passes binds nothing outside a caller that calls every callable', function () {
    expect(passedArgumentTypes(PassedArgumentEdgesResource::class, 'spreadCallables'))->toBe([
        'held' => 'unknown',
        'held_closure' => 'unknown',
        'held_callable' => 'Record<string, never>',
    ]);
});

test('the signature is empty for a call that binds nothing, and one for two arguments that bind alike', function () {
    $binder = resolve(ParameterBinder::class);
    $held = $binder->passedBinding(['type' => 'unknown', 'optional' => false], ReceiverType::of(Post::class));
    $typed = $binder->passedBinding(['type' => 'Post', 'optional' => true, 'modelFqcn' => Post::class], ReceiverType::of(Post::class));
    $nullable = $binder->passedBinding(['type' => 'Post | null', 'optional' => false, 'modelFqcn' => Post::class], ReceiverType::of(Post::class));

    expect(CallSite::signature([]))->toBe('')
        ->and($binder->passedBinding(['type' => 'unknown', 'optional' => false], null))->toBeNull()
        ->and($held)->toBe(['model' => Post::class, 'classes' => null, 'elementModel' => null, 'value' => null, 'unsaved' => false, 'refines' => false])
        ->and(CallSite::signature(['post' => $held]))->toBe(CallSite::signature(['post' => $typed]))
        ->and(CallSite::signature(['post' => $held]))->not->toBe(CallSite::signature(['post' => $nullable]));
});

test('a call binds nothing in a method whose parameters all declare a type, and resolves none of its arguments', function () {
    $scope = new AnalysisScope(new ReflectionClass(HelperParameterKindsResource::class), Post::class);
    $call = new MethodCall(new Variable('this'), 'scalar', [new Arg(new String_('5'))]);

    expect(new CallSite($call, $scope, chainHandlersThrowingEngine())->passedTo(new ReflectionMethod(HelperParameterKindsResource::class, 'scalar')))
        ->toBe([]);
});
