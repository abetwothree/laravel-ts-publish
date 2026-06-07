<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Collectors\BroadcastChannelsCollector;
use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastChannelsTransformer;

/**
 * These tests document a key design point: the join() parameter types on a
 * channel class (Model, Enum, primitive) have NO effect on the TypeScript
 * output. The channel name string is the only input the transformer sees.
 *
 * Laravel resolves join() parameters at runtime (route model binding, enum
 * coercion, etc.), but the TypeScript generator only cares about the channel
 * name pattern. Every wildcard {segment} becomes `string | number` regardless
 * of whether PHP resolves it to an Eloquent model, a backed enum, or an int.
 */
describe('BroadcastChannelsTransformer — class-based channel parameter types', function () {

    /**
     * No parameters (static channel) — the original class-based example.
     * PublicAnnouncementsChannel::join(User $user) has no wildcards.
     */
    describe('no parameters — static channel name', function () {
        it('produces a plain template literal const entry without a function wrapper', function () {
            $transformer = new BroadcastChannelsTransformer;
            $dto = $transformer->transform(collect(['public-announcements']));

            expect($dto->constBody)
                ->toContain('"public-announcements": `public-announcements` as const');
        });

        it('produces a single-member type union', function () {
            $transformer = new BroadcastChannelsTransformer;
            $dto = $transformer->transform(collect(['public-announcements']));

            expect($dto->typeUnion)
                ->toBe('export type BroadcastChannel = `public-announcements`;');
        });
    });

    /**
     * 1 model — OrderChannel::join(User $user, Order $order).
     * Laravel binds {orderId} to an Order model via route model binding.
     * TypeScript output: still `string | number` for the wildcard.
     */
    describe('1 model parameter — join(User $user, Order $order)', function () {
        it('produces a function wrapper with string|number param regardless of model binding', function () {
            $transformer = new BroadcastChannelsTransformer;
            $dto = $transformer->transform(collect(['order.{orderId}']));

            expect($dto->constBody)
                ->toContain('order: (orderId: string | number) => `order.${orderId}` as const');
        });

        it('produces a template literal union type for the wildcard', function () {
            $transformer = new BroadcastChannelsTransformer;
            $dto = $transformer->transform(collect(['order.{orderId}']));

            expect($dto->typeUnion)
                ->toContain('`order.${string | number}`');
        });
    });

    /**
     * 2 related models — PostCommentChannel::join(User $user, Post $post, Comment $comment).
     * Both {postId} and {commentId} are bound to models at runtime.
     * TypeScript output: nested function wrappers with `string | number` params.
     */
    describe('2 model parameters — join(User $user, Post $post, Comment $comment)', function () {
        it('produces nested function wrappers for each model wildcard', function () {
            $transformer = new BroadcastChannelsTransformer;
            $dto = $transformer->transform(collect(['post.{postId}.comment.{commentId}']));

            expect($dto->constBody)
                ->toContain('post: (postId: string | number) => ({')
                ->toContain('comment: (commentId: string | number) => `post.${postId}.comment.${commentId}` as const');
        });

        it('produces a template literal union type with both model wildcards', function () {
            $transformer = new BroadcastChannelsTransformer;
            $dto = $transformer->transform(collect(['post.{postId}.comment.{commentId}']));

            expect($dto->typeUnion)
                ->toContain('`post.${string | number}.comment.${string | number}`');
        });
    });

    /**
     * Int-backed enum — OrderStatusChannel::join(User $user, Status $status).
     * Laravel coerces the {statusId} wildcard integer to a Status enum instance.
     * TypeScript output: `string | number` — the backing type (int) is PHP-only.
     */
    describe('int-backed enum parameter — join(User $user, Status $status)', function () {
        it('produces a function wrapper with string|number param, not int', function () {
            $transformer = new BroadcastChannelsTransformer;
            $dto = $transformer->transform(collect(['order-status.{statusId}']));

            // TypeScript does NOT know the wildcard is an int enum — it is string|number
            expect($dto->constBody)
                ->toContain('"order-status": (statusId: string | number) => `order-status.${statusId}` as const');
        });

        it('uses a quoted key because "order-status" contains a hyphen', function () {
            $transformer = new BroadcastChannelsTransformer;
            $dto = $transformer->transform(collect(['order-status.{statusId}']));

            expect($dto->constBody)->toContain('"order-status":');
        });

        it('produces a template literal union type for the int-backed enum wildcard', function () {
            $transformer = new BroadcastChannelsTransformer;
            $dto = $transformer->transform(collect(['order-status.{statusId}']));

            expect($dto->typeUnion)
                ->toContain('`order-status.${string | number}`');
        });
    });

    /**
     * String-backed enum — ColorThemeChannel::join(User $user, Color $color).
     * Laravel coerces the {colorId} wildcard string to a Color enum instance.
     * TypeScript output: `string | number` — the backing type (string) is PHP-only.
     */
    describe('string-backed enum parameter — join(User $user, Color $color)', function () {
        it('produces a function wrapper with string|number param, not string', function () {
            $transformer = new BroadcastChannelsTransformer;
            $dto = $transformer->transform(collect(['color-theme.{colorId}']));

            // TypeScript does NOT know the wildcard is a string enum — it is string|number
            expect($dto->constBody)
                ->toContain('"color-theme": (colorId: string | number) => `color-theme.${colorId}` as const');
        });

        it('produces a template literal union type for the string-backed enum wildcard', function () {
            $transformer = new BroadcastChannelsTransformer;
            $dto = $transformer->transform(collect(['color-theme.{colorId}']));

            expect($dto->typeUnion)
                ->toContain('`color-theme.${string | number}`');
        });
    });

    /**
     * Non-backed (pure) enum — RoleDashboardChannel::join(User $user, Role $role).
     * Laravel matches {roleId} by enum case name (e.g. 'Admin'). No scalar value.
     * TypeScript output: `string | number` — enum resolution is entirely PHP-side.
     */
    describe('non-backed enum parameter — join(User $user, Role $role)', function () {
        it('produces a function wrapper with string|number param for a pure enum wildcard', function () {
            $transformer = new BroadcastChannelsTransformer;
            $dto = $transformer->transform(collect(['role-dashboard.{roleId}']));

            expect($dto->constBody)
                ->toContain('"role-dashboard": (roleId: string | number) => `role-dashboard.${roleId}` as const');
        });

        it('produces a template literal union type for the non-backed enum wildcard', function () {
            $transformer = new BroadcastChannelsTransformer;
            $dto = $transformer->transform(collect(['role-dashboard.{roleId}']));

            expect($dto->typeUnion)
                ->toContain('`role-dashboard.${string | number}`');
        });
    });

    /**
     * Primitives — TeamRoomChannel::join(User $user, int $teamId, string $roomName).
     * Both wildcards are passed as plain PHP primitives (int + string).
     * TypeScript output: `string | number` for both — same as every other case.
     */
    describe('primitive parameters — join(User $user, int $teamId, string $roomName)', function () {
        it('produces nested function wrappers for int and string primitives', function () {
            $transformer = new BroadcastChannelsTransformer;
            $dto = $transformer->transform(collect(['teams.{teamId}.rooms.{roomName}']));

            // Both int and string primitives become string|number in TypeScript
            expect($dto->constBody)
                ->toContain('teams: (teamId: string | number) => ({')
                ->toContain('rooms: (roomName: string | number) => `teams.${teamId}.rooms.${roomName}` as const');
        });

        it('produces a template literal union type for both primitive wildcards', function () {
            $transformer = new BroadcastChannelsTransformer;
            $dto = $transformer->transform(collect(['teams.{teamId}.rooms.{roomName}']));

            expect($dto->typeUnion)
                ->toContain('`teams.${string | number}.rooms.${string | number}`');
        });
    });

    /**
     * All 10 workbench channels together — verifies the full output shape.
     */
    describe('full workbench fixture with all class-based channel types', function () {
        it('collects all 10 workbench channels including the 6 new class-based ones', function () {
            $collector = resolve(BroadcastChannelsCollector::class);
            $channels = $collector->collect();

            // Original 4
            expect($channels)->toContain('orders.{orderId}')
                ->and($channels)->toContain('user.{userId}.notifications')
                ->and($channels)->toContain('chat.{roomId}.messages')
                ->and($channels)->toContain('public-announcements');

            // 6 new class-based channels
            expect($channels)->toContain('order.{orderId}')
                ->and($channels)->toContain('post.{postId}.comment.{commentId}')
                ->and($channels)->toContain('order-status.{statusId}')
                ->and($channels)->toContain('color-theme.{colorId}')
                ->and($channels)->toContain('role-dashboard.{roleId}')
                ->and($channels)->toContain('teams.{teamId}.rooms.{roomName}');
        });

        it('produces identical TypeScript wildcard types regardless of join() parameter type', function () {
            $transformer = new BroadcastChannelsTransformer;
            $dto = $transformer->transform(collect([
                'order.{orderId}',           // Model
                'post.{postId}.comment.{commentId}', // 2 Models
                'order-status.{statusId}',   // Int-backed enum
                'color-theme.{colorId}',     // String-backed enum
                'role-dashboard.{roleId}',   // Non-backed enum
                'teams.{teamId}.rooms.{roomName}', // Primitives
            ]));

            // Every wildcard produces `string | number` — parameter type is irrelevant
            $typeUnion = $dto->typeUnion;
            expect(substr_count($typeUnion, 'string | number'))->toBeGreaterThanOrEqual(8);
        });
    });
});
