<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastChannelsTransformer;

test('empty collection produces isEmpty DTO', function () {
    $transformer = new BroadcastChannelsTransformer;
    $dto = $transformer->transform(collect());

    expect($dto->isEmpty)->toBeTrue()
        ->and($dto->typeUnion)->toBe('')
        ->and($dto->constBody)->toBe('');
});

test('single static channel produces single-member type union', function () {
    $transformer = new BroadcastChannelsTransformer;
    $dto = $transformer->transform(collect(['public-announcements']));

    expect($dto->isEmpty)->toBeFalse()
        ->and($dto->typeUnion)->toBe('export type BroadcastChannel = `public-announcements`;');
});

test('static channel const key is quoted when it contains hyphens', function () {
    $transformer = new BroadcastChannelsTransformer;
    $dto = $transformer->transform(collect(['public-announcements']));

    expect($dto->constBody)->toContain('"public-announcements": `public-announcements` as const');
});

test('static channel const key is not quoted when it is a valid identifier', function () {
    $transformer = new BroadcastChannelsTransformer;
    $dto = $transformer->transform(collect(['announcements']));

    expect($dto->constBody)->toContain('announcements: `announcements` as const');
});

test('channel ending in a param produces a function wrapper', function () {
    $transformer = new BroadcastChannelsTransformer;
    $dto = $transformer->transform(collect(['orders.{orderId}']));

    expect($dto->constBody)
        ->toContain('orders: (orderId: string | number) => `orders.${orderId}` as const');
});

test('param in middle segment produces nested object wrapped in function', function () {
    $transformer = new BroadcastChannelsTransformer;
    $dto = $transformer->transform(collect(['user.{userId}.notifications']));

    expect($dto->constBody)
        ->toContain('user: (userId: string | number) => ({')
        ->toContain('notifications: `user.${userId}.notifications` as const');
});

test('type union uses template literal type for parametrised channel', function () {
    $transformer = new BroadcastChannelsTransformer;
    $dto = $transformer->transform(collect(['orders.{orderId}']));

    expect($dto->typeUnion)->toContain('`orders.${string | number}`');
});

test('multiple channels produce multi-line type union', function () {
    $transformer = new BroadcastChannelsTransformer;
    $dto = $transformer->transform(collect([
        'orders.{orderId}',
        'public-announcements',
    ]));

    expect($dto->typeUnion)
        ->toContain('export type BroadcastChannel =')
        ->toContain('| `orders.${string | number}`')
        ->toContain('| `public-announcements`;');
});

test('full workbench fixture produces expected TypeScript structure', function () {
    // This fixture mirrors the workbench channels.php, including 'chat.{roomId}'
    // alongside 'chat.{roomId}.messages' to exercise the $channel accessor fix.
    $transformer = new BroadcastChannelsTransformer;
    $dto = $transformer->transform(collect([
        'orders.{orderId}',
        'user.{userId}.notifications',
        'chat.{roomId}',
        'chat.{roomId}.messages',
        'public-announcements',
    ]));

    expect($dto->isEmpty)->toBeFalse();

    expect($dto->typeUnion)
        ->toContain('| `orders.${string | number}`')
        ->toContain('| `user.${string | number}.notifications`')
        ->toContain('| `chat.${string | number}`')
        ->toContain('| `chat.${string | number}.messages`')
        ->toContain('| `public-announcements`;');

    expect($dto->constBody)
        ->toContain('orders: (orderId: string | number) => `orders.${orderId}` as const')
        ->toContain('user: (userId: string | number) => ({')
        ->toContain('notifications: `user.${userId}.notifications` as const')
        ->toContain('chat: (roomId: string | number) => ({')
        ->toContain('$channel: `chat.${roomId}` as const')
        ->toContain('messages: `chat.${roomId}.messages` as const')
        ->toContain('"public-announcements": `public-announcements` as const');
});

test('deeply nested channel produces double function wrappers', function () {
    $transformer = new BroadcastChannelsTransformer;
    $dto = $transformer->transform(collect(['team.{teamId}.user.{userId}.notification']));

    expect($dto->constBody)
        ->toContain('team: (teamId: string | number) => ({')
        ->toContain('user: (userId: string | number) => ({')
        ->toContain('notification: `team.${teamId}.user.${userId}.notification` as const');
});

test('channels sharing a static prefix share the same const key', function () {
    $transformer = new BroadcastChannelsTransformer;
    $dto = $transformer->transform(collect([
        'chat.{roomId}.general',
        'chat.{roomId}.private',
    ]));

    // Only one 'chat' entry — both children nested under it
    expect(substr_count($dto->constBody, 'chat:'))->toBe(1);
    expect($dto->constBody)
        ->toContain('general: `chat.${roomId}.general` as const')
        ->toContain('private: `chat.${roomId}.private` as const');
});

test('channel that is also a prefix of another channel exposes both a $channel accessor and its children', function () {
    // 'chat.{roomId}' is a terminal channel AND a prefix of 'chat.{roomId}.messages'.
    // The $channel key must appear in the nested object so both are accessible.
    $transformer = new BroadcastChannelsTransformer;
    $dto = $transformer->transform(collect([
        'chat.{roomId}',
        'chat.{roomId}.messages',
    ]));

    // Both appear in the union type
    expect($dto->typeUnion)
        ->toContain('`chat.${string | number}`')
        ->toContain('`chat.${string | number}.messages`');

    // The nested object has a $channel accessor for the parent channel value
    expect($dto->constBody)
        ->toContain('chat: (roomId: string | number) => ({')
        ->toContain('$channel: `chat.${roomId}` as const')
        ->toContain('messages: `chat.${roomId}.messages` as const');

    // Only one 'chat' key in the output
    expect(substr_count($dto->constBody, 'chat:'))->toBe(1);
});

test('conflicting parameter names for the same static segment throw InvalidArgumentException', function () {
    // 'orders.{orderId}' and 'orders.{slug}.timeline' both route through the
    // static 'orders' segment, but assign it different wildcard param names.
    // The transformer must reject this rather than silently generating broken TS.
    $transformer = new BroadcastChannelsTransformer;
    $transformer->transform(collect([
        'orders.{orderId}',
        'orders.{slug}.timeline',
    ]));
})->throws(InvalidArgumentException::class, 'conflicting parameter names');

test('non-overlapping channels are not affected by the selfChannel logic', function () {
    // Regression guard: channels that are not prefixes of each other must
    // not gain a $channel key.
    $transformer = new BroadcastChannelsTransformer;
    $dto = $transformer->transform(collect([
        'user.{userId}.notifications',
        'chat.{roomId}.messages',
    ]));

    expect($dto->constBody)->not->toContain('$channel');
});
