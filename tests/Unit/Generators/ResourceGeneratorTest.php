<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Http\Resources\GuardClauseClosureResource;
use Workbench\App\Http\Resources\OrderResource;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\SpreadWithClosureResource;
use Workbench\App\Http\Resources\SpreadWithGuardClauseClosureResource;
use Workbench\App\Http\Resources\SpreadWithGuardDoubleClosureReturnResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\Blog\Http\Resources\ApiArticleResource;

test('generates PostResource typescript content', function () {
    config()->set('ts-publish.output_to_files', false);

    $generator = resolve(ResourceGenerator::class, ['findable' => PostResource::class]);

    expect($generator->content)
        ->toContain("import { type AsEnum } from '@tolki/ts'")
        ->toContain("import { Priority, Status, Visibility } from '../../enums'")
        ->toContain('export interface PostResource')
        ->toContain('id: number')
        ->toContain('title: string')
        ->toContain('content: string')
        ->toContain('status: AsEnum<typeof Status>')
        ->toContain('status_new: AsEnum<typeof Status>')
        ->toContain('visibility: AsEnum<typeof Visibility> | null')
        ->toContain('visibility_new: AsEnum<typeof Visibility> | null')
        ->toContain('priority: AsEnum<typeof Priority> | null')
        ->toContain('priority_new: AsEnum<typeof Priority> | null');
});

test('generates PostResource with type imports when tolki disabled', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums.use_tolki_package', false);

    $generator = resolve(ResourceGenerator::class, ['findable' => PostResource::class]);

    expect($generator->content)
        ->toContain("import type { PriorityType, StatusType, VisibilityType } from '../../enums'")
        ->toContain('status: StatusType')
        ->toContain('status_new: StatusType')
        ->toContain('visibility: VisibilityType | null')
        ->toContain('visibility_new: VisibilityType | null')
        ->toContain('priority: PriorityType | null')
        ->toContain('priority_new: PriorityType | null')
        ->not->toContain('@tolki/ts');
});

test('generates UserResource with optional properties', function () {
    config()->set('ts-publish.output_to_files', false);

    $generator = resolve(ResourceGenerator::class, ['findable' => UserResource::class]);

    expect($generator->content)
        ->toContain("import { type AsEnum } from '@tolki/ts'")
        ->toContain("import { Role } from '../../enums'")
        ->toContain("import type { PostResource } from '.'")
        ->toContain('export interface UserResource')
        ->toContain('id: number')
        ->toContain('name: string')
        ->toContain('role: AsEnum<typeof Role> | null')
        ->toContain('profile?:')
        ->toContain('phone?:')
        ->toContain('avatar?:')
        ->toContain('posts_count?: number')
        ->toContain('comments_count?: number');
});

test('generates CommentResource with TsResourceCasts overrides', function () {
    config()->set('ts-publish.output_to_files', false);

    $generator = resolve(ResourceGenerator::class, ['findable' => CommentResource::class]);

    expect($generator->content)
        ->toContain("import type { PostResource, UserResource } from '.'")
        ->toContain('export interface CommentResource')
        ->toContain('metadata: Record<string, unknown>')
        ->toContain('flagged_at?: string | null')
        ->toContain('author?: UserResource')
        ->toContain('post?: PostResource')
        ->not->toContain('@tolki/ts');
});

test('generates OrderResource with conditional methods', function () {
    config()->set('ts-publish.output_to_files', false);

    $generator = resolve(ResourceGenerator::class, ['findable' => OrderResource::class]);

    expect($generator->content)
        ->toContain("import { type AsEnum } from '@tolki/ts'")
        ->toContain("import { Currency, OrderStatus } from '../../enums'")
        ->toContain('export interface OrderResource')
        ->toContain('status: AsEnum<typeof OrderStatus>')
        ->toContain('currency: AsEnum<typeof Currency>')
        ->toContain('items_count?: number')
        ->toContain('total_avg?: number')
        ->toContain('paid_at?:')
        ->toContain('shipped_at?:')
        ->toContain('delivered_at?:');
});

test('exposes transformer property', function () {
    config()->set('ts-publish.output_to_files', false);

    $generator = resolve(ResourceGenerator::class, ['findable' => PostResource::class]);

    expect($generator->transformer)->toBeInstanceOf(ResourceTransformer::class)
        ->and($generator->transformer->resourceName)->toBe('PostResource');
});

test('exposes findable property', function () {
    config()->set('ts-publish.output_to_files', false);

    $generator = resolve(ResourceGenerator::class, ['findable' => PostResource::class]);

    expect($generator->findable)->toBe(PostResource::class);
});

test('filename delegates to transformer', function () {
    config()->set('ts-publish.output_to_files', false);

    $generator = resolve(ResourceGenerator::class, ['findable' => PostResource::class]);

    expect($generator->filename())->toBe('post-resource');
});

test('generates GuardClauseClosureResource with guard clause producing union with null', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums_use_tolki_package', false);

    $generator = resolve(ResourceGenerator::class, ['findable' => GuardClauseClosureResource::class]);

    expect($generator->content)
        ->toContain('export interface GuardClauseClosureResource')
        ->toContain('id: number')
        ->toContain('total: number')
        ->toContain('buyer?: { name: string; email: string } | null');
});

test('generates SpreadWithClosureResource with parent spread and closure whenLoaded', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums_use_tolki_package', false);

    $generator = resolve(ResourceGenerator::class, ['findable' => SpreadWithClosureResource::class]);

    expect($generator->content)
        ->toContain("import type { MembershipLevelType, RoleType } from '../../enums'")
        ->toContain('export interface SpreadWithClosureResource')
        // parent::toArray() spread model attributes
        ->toContain('id: number')
        ->toContain('name: string')
        ->toContain('email: string')
        ->toContain('role: RoleType | null')
        // whenLoaded closure property
        ->toContain('metadata?: { profile_bio: string | null');
});

test('generates SpreadWithGuardClauseClosureResource with guard clause and parent spread', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums_use_tolki_package', false);

    $generator = resolve(ResourceGenerator::class, ['findable' => SpreadWithGuardClauseClosureResource::class]);

    expect($generator->content)
        ->toContain("import type { CurrencyType, OrderStatusType, PaymentMethodType, RoleType } from '../../enums'")
        ->toContain("import type { OrderItem, User } from '../../models'")
        ->toContain('export interface SpreadWithGuardClauseClosureResource')
        // parent::toArray() spread model attributes
        ->toContain('id: number')
        ->toContain('status: OrderStatusType')
        ->toContain('currency: CurrencyType')
        ->toContain('user: User')
        ->toContain('items: OrderItem[]')
        // guard clause closure produces object shape | null
        ->toContain('customer?: { name: string; email: string; phone: string | null; avatar: string | null; role: RoleType | null; is_premium: boolean; name_titled: string; morph: string } | null');
});

test('generates SpreadWithGuardDoubleClosureReturnResource with union of two shapes plus null', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums_use_tolki_package', false);

    $generator = resolve(ResourceGenerator::class, ['findable' => SpreadWithGuardDoubleClosureReturnResource::class]);

    expect($generator->content)
        ->toContain("import type { CurrencyType, OrderStatusType, PaymentMethodType, RoleType } from '../../enums'")
        ->toContain("import type { OrderItem, User } from '../../models'")
        ->toContain('export interface SpreadWithGuardDoubleClosureReturnResource')
        // parent::toArray() spread model attributes
        ->toContain('id: number')
        ->toContain('ulid: string')
        ->toContain('status: OrderStatusType')
        ->toContain('payment_method: PaymentMethodType | null')
        ->toContain('currency: CurrencyType')
        ->toContain('user: User')
        ->toContain('items: OrderItem[]')
        // Union: two distinct object shapes + null from guard clause
        ->toContain('customer?: { name: string; initials: string; email: string; phone: string | null; avatar: string | null; role: RoleType | null; is_premium: boolean } | { name: string; email: string; phone: string | null; avatar: string | null; role: RoleType | null; is_premium: boolean; name_titled: string; morph: string } | null');
});

test('generates ApiArticleResource with parent trait spreads and enum types', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

    $generator = resolve(ResourceGenerator::class, ['findable' => ApiArticleResource::class]);

    expect($generator->content)
        ->toContain('export interface ApiArticleResource')
        ->toContain('morphValue: string')
        ->toContain('firstName: string')
        ->toContain('title: string')
        ->toContain('slug: string')
        ->toContain('excerpt: string | null')
        ->toContain('body: string')
        ->toContain('is_featured: boolean')
        ->toContain('author?:');
});
