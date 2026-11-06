<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events\ConditionableCoercedEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events\ConditionableDeclinedEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events\ConditionablePayloadEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events\ConditionableRebindingEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ConditionableBoxResource;
use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastEventTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Illuminate\Http\Request;
use Workbench\App\Models\Post;

/**
 * Each key a method publishes, as `name => type`, `?` marking an optional key.
 *
 * @param  class-string  $class
 * @return array<string, string>
 */
function conditionableCallKeys(string $class, string $method = 'broadcastWith'): array
{
    $keys = [];

    foreach (resolve(AstEngine::class)->analyzeMethod($class, $method)->properties as $property) {
        $keys[$property['name'].($property['optional'] ? '?' : '')] = $property['type'];
    }

    return $keys;
}

/**
 * What json_encode() sends for each key of the payload event over a post, pinned or not.
 *
 * @return array<string, mixed>
 */
function conditionablePayloadSent(bool $pinned): array
{
    $post = (new Post)->forceFill(['id' => 7, 'title' => 'T', 'is_pinned' => $pinned]);

    return json_decode((string) json_encode(new ConditionablePayloadEvent($post)->broadcastWith()), true);
}

// Conditionable::when() returns `$callback($this, $value) ?? $this` where the value holds, else
// `$default($this, $value) ?? $this`, else `$this`; unless() swaps the arms. So a key is the callback's value, the
// default's, or the object's own JSON in place of a null either gives and of a default not passed. It is always sent.
it('types Conditionable::when() and unless() as the callback, the default or the receiver itself', function () {
    $event = '{ post: Post }';

    expect(conditionableCallKeys(ConditionablePayloadEvent::class))->toBe([
        'label' => 'string | '.$event,
        'with_default' => 'string',
        'mixed_default' => 'string | number',
        'unless' => 'string | '.$event,
        'unless_default' => 'string | number',
        'null_callback' => 'string | '.$event,
        'null_only' => $event,
        'null_default' => 'string | '.$event,
        'named' => 'string | number',
        'typed_callback' => 'string | '.$event,
        'typed_self' => 'string | '.$event,
        'passed_value' => 'boolean | '.$event,
        'first_class' => 'string | '.$event,
        'variadic' => 'number | '.$event,
        'variadic_list' => '('.$event.' | boolean)[] | '.$event,
        'mapped' => '(string | '.$event.')[]',
        'box' => 'string | { name: string }',
        'box_param' => 'string | { name: string }',
        'box_itself' => '{ name: string }',
        'void_or_default' => 'string | '.$event,
    ])->and(AnalysisWarnings::all())->toBe([]);

    $pinned = conditionablePayloadSent(true);
    $unpinned = conditionablePayloadSent(false);

    expect([$pinned['label'], $unpinned['label']['post']['id']])->toBe(['active', 7])
        ->and([$pinned['with_default'], $unpinned['with_default']])->toBe(['a', 'b'])
        ->and([$pinned['mixed_default'], $unpinned['mixed_default']])->toBe(['a', 1])
        ->and([$pinned['unless']['post']['id'], $unpinned['unless']])->toBe([7, 'off'])
        ->and([$pinned['unless_default'], $unpinned['unless_default']])->toBe([2, 'a'])
        ->and([$pinned['null_callback']['post']['id'], $unpinned['null_callback']])->toBe([7, 'b'])
        ->and([$pinned['null_only']['post']['id'], $unpinned['null_only']['post']['id']])->toBe([7, 7])
        ->and([$pinned['null_default'], $unpinned['null_default']['post']['id']])->toBe(['a', 7])
        ->and([$pinned['named'], $unpinned['named']])->toBe(['a', 2])
        ->and([$pinned['typed_self'], $unpinned['typed_self']['post']['id']])->toBe(['T', 7])
        ->and([$pinned['passed_value'], $unpinned['passed_value']['post']['id']])->toBe([true, 7])
        ->and([$pinned['first_class'], $pinned['variadic']])->toBe(['label', 2])
        ->and([$pinned['variadic_list'][0]['post']['id'], $pinned['variadic_list'][1]])->toBe([7, true])
        ->and([$pinned['mapped'][0]['post']['id'], $pinned['mapped'][1]])->toBe([7, 'big'])
        ->and([$pinned['box'], $unpinned['box']])->toBe(['boxed', 'boxed'])
        ->and([$pinned['box_param'], $unpinned['box_param']])->toBe(['box', ['name' => 'box']])
        ->and([$pinned['box_itself'], $unpinned['box_itself']])->toBe([['name' => 'box'], ['name' => 'box']])
        ->and([$pinned['void_or_default']['post']['id'], $unpinned['void_or_default']])->toBe([7, 'b']);
});

// Laravel calls a callable with the object and the value the call holds, so neither a parameter named like a variable
// either reads nor a write in the callable's body changes them: each is read as the caller reads it. A variable a
// closure captures by reference reads `unknown` wherever the closure may have written it, so that key declines.
it('passes a callable the object and the value as the caller reads them', function () {
    $sent = fn (bool $pinned): array => json_decode((string) json_encode(new ConditionableRebindingEvent(
        (new Post)->forceFill(['id' => 7, 'is_pinned' => $pinned]),
    )->broadcastWith()), true);
    [$pinned, $unpinned] = [$sent(true), $sent(false)];

    expect(conditionableCallKeys(ConditionableRebindingEvent::class))->toBe([
        'assigned' => 'boolean | { post: Post }',
        'looped' => 'boolean | { post: Post }',
        'receiver_assigned' => 'string | { id: string }',
        'referenced' => 'unknown',
        'receiver_shadow' => 'string | { name: string }',
        'receiver_shadow_rewritten' => 'string | { name: string }',
        'condition_shadow' => 'boolean | { post: Post }',
        'condition_shadow_rewritten' => 'boolean | { post: Post }',
        'default_shadow' => 'string | boolean',
        'default_shadow_rewritten' => 'string | boolean',
        'value_shadow' => 'boolean | { post: Post }',
        'rest_shadow' => 'boolean[] | { post: Post }',
        'receiver_second_shadow' => 'string | { id: string }',
    ])->and([$pinned['assigned'], $pinned['looped'], $pinned['referenced']])->toBe([true, true, true])
        ->and([$pinned['receiver_shadow'], $unpinned['receiver_shadow'], $pinned['receiver_shadow_rewritten']])->toBe(['box', ['name' => 'box'], 'box'])
        ->and([$pinned['condition_shadow'], $pinned['condition_shadow_rewritten'], $unpinned['condition_shadow']['post']['id']])->toBe([true, true, 7])
        ->and([$pinned['default_shadow'], $unpinned['default_shadow'], $unpinned['default_shadow_rewritten']])->toBe(['a', false, false])
        ->and([$pinned['value_shadow'], $pinned['rest_shadow'], $pinned['receiver_second_shadow']])->toBe([true, [true], 'n'])
        ->and([$unpinned['assigned']['post']['id'], $unpinned['looped']['post']['id'], $unpinned['referenced']['post']['id']])->toBe([7, 7, 7])
        ->and([$pinned['receiver_assigned'], $unpinned['receiver_assigned']])->toBe(['n', 'n']);
});

// Laravel's own code, which declares no strict types, calls each callable, so PHP converts the value to a scalar
// parameter's type, and keeps one that already fits it; a value it cannot convert throws, sending nothing.
it('binds a scalar callable parameter to the type PHP converts the passed value to', function () {
    $sent = json_decode((string) json_encode(new ConditionableCoercedEvent(
        (new Post)->forceFill(['id' => 7, 'is_pinned' => true]),
    )->broadcastWith()), true);

    expect(conditionableCallKeys(ConditionableCoercedEvent::class))->toBe([
        'to_int' => 'number | { post: Post }',
        'to_string' => 'string | { post: Post }',
        'to_bool' => 'boolean | { post: Post }',
        'to_nullable_int' => 'number | { post: Post }',
        'null_to_nullable_int' => 'string | { post: Post }',
        'int_to_float' => 'number | { post: Post }',
        'untyped' => 'string | { post: Post }',
        'to_int_list' => '({ post: Post } | string)[] | { post: Post }',
        'to_int_rest' => 'number[] | { post: Post }',
    ])->and([$sent['to_int'], $sent['to_string'], $sent['to_bool'], $sent['to_nullable_int']])->toBe([5, '5', true, 5])
        ->and([$sent['null_to_nullable_int']['post']['id'], $sent['int_to_float'], $sent['untyped']])->toBe([7, 5, '5'])
        ->and([$sent['to_int_list'][1], $sent['to_int_rest']])->toBe(['5', [5]]);
});

// The event's own JSON names the post, so the key that sends it imports the model.
it('imports the model the receiver\'s own JSON names', function () {
    $transformer = app(BroadcastEventTransformer::class, ['findable' => ConditionablePayloadEvent::class]);

    expect($transformer->properties['null_only']['optional'])->toBeFalse()
        ->and($transformer->properties['null_only']['type'])->toContain('Post')
        ->and(array_merge(...array_values($transformer->typeImports)))->toContain('Post');
});

// With 0 or 1 arguments when() returns a HigherOrderWhenProxy. A class's own when() runs instead, a model sends the
// call to its query or runs its own, and a query's or a collection's when() is left to the readers that type it. A
// callback no reader types, or an object whose JSON none does, would drop an arm Laravel sends.
it('keeps the answers it had for a when() that is not Conditionable\'s two-callback form', function () {
    expect(conditionableCallKeys(ConditionableDeclinedEvent::class))->toBe([
        'proxy' => 'unknown',
        'own_when' => 'number',
        'model' => 'unknown',
        'conditionable_model' => 'unknown',
        'query' => '{ pendingAttributes: Record<string, unknown> | never[] }',
        'query_values' => 'unknown',
        'queried' => 'Post[]',
        'listed' => 'unknown',
        'opaque' => 'unknown',
        'fluent' => 'unknown',
        'unknown_arm' => 'unknown',
        'dropped_arm' => 'unknown',
        'dropped_in_helper' => 'unknown',
        'uncalled_closure' => 'unknown',
    ]);
});

// A closure a callable returns is sent as `{}`, as nothing calls it, and an arm read with a value left out or an
// `unknown` arm would publish a type Laravel does not keep to.
it('declines Conditionable::when() where a callable cannot be read for what Laravel sends', function () {
    $sent = fn (bool $pinned): array => json_decode((string) json_encode(new ConditionableDeclinedEvent(
        (new Post)->forceFill(['id' => 7, 'is_pinned' => $pinned]),
    )->broadcastWith()), true);
    $pinned = $sent(true);

    expect([$pinned['dropped_arm'], $pinned['dropped_in_helper'], $pinned['uncalled_closure']])->toBe([['a' => 1], [1], []])
        ->and($pinned['unknown_arm'])->toBe(['a' => 'x'])
        ->and($sent(false)['dropped_arm']['post']['id'])->toBe(7)
        ->and(AnalysisWarnings::all())->toBe([]);

    expect(conditionableCallKeys(ConditionableDeclinedEvent::class))->toMatchArray([
        'unknown_arm' => 'unknown',
        'dropped_arm' => 'unknown',
        'dropped_in_helper' => 'unknown',
        'uncalled_closure' => 'unknown',
    ])->and(AnalysisWarnings::all())->toBe([]);
});

// A resource's own `$this->when()` stays JsonResource's, which leaves the key out, while a box it holds runs
// Conditionable's, whose key is always sent, and null where a `?->` meets no box.
it('types Conditionable::when() on a value an API resource holds, beside the resource\'s own when()', function () {
    $transformer = new ResourceTransformer(ConditionableBoxResource::class);
    $resolve = fn (bool $pinned): array => new ConditionableBoxResource(
        (new Post)->forceFill(['id' => 7, 'is_pinned' => $pinned]),
    )->resolve(Request::create('/'));

    expect(array_map(fn (array $property): array => [$property['type'], $property['optional']], $transformer->properties))->toBe([
        'box_when' => ['string | { name: string }', false],
        'box_default' => ['string', false],
        'own_when' => ['string', true],
        'spare' => ['string | { name: string } | null', false],
    ])->and($resolve(true))->toBe(['box_when' => 'boxed', 'box_default' => 'a', 'own_when' => 'active', 'spare' => null])
        ->and(json_decode((string) json_encode($resolve(false)), true))->toBe(['box_when' => ['name' => 'box'], 'box_default' => 'b', 'spare' => null]);
});
