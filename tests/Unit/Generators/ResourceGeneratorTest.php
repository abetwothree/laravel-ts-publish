<?php

use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Http\Resources\OrderResource;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\UserResource;

test('generates PostResource typescript content', function () {
    config()->set('ts-publish.output_to_files', false);

    $generator = resolve(ResourceGenerator::class, ['findable' => PostResource::class]);

    expect($generator->content)
        ->toContain("import { type AsEnum } from '@tolki/enum'")
        ->toContain("import { Priority, Status, Visibility } from '../enums'")
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
    config()->set('ts-publish.enums_use_tolki_package', false);

    $generator = resolve(ResourceGenerator::class, ['findable' => PostResource::class]);

    expect($generator->content)
        ->toContain("import type { PriorityType, StatusType, VisibilityType } from '../enums'")
        ->toContain('status: StatusType')
        ->toContain('status_new: StatusType')
        ->toContain('visibility: VisibilityType | null')
        ->toContain('visibility_new: VisibilityType | null')
        ->toContain('priority: PriorityType | null')
        ->toContain('priority_new: PriorityType | null')
        ->not->toContain('@tolki/enum');
});

test('generates UserResource with optional properties', function () {
    config()->set('ts-publish.output_to_files', false);

    $generator = resolve(ResourceGenerator::class, ['findable' => UserResource::class]);

    expect($generator->content)
        ->toContain("import { type AsEnum } from '@tolki/enum'")
        ->toContain("import { Role } from '../enums'")
        ->toContain("import type { PostResource } from './'")
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
        ->toContain("import type { PostResource, UserResource } from './'")
        ->toContain('export interface CommentResource')
        ->toContain('metadata: Record<string, unknown>')
        ->toContain('flagged_at?: string | null')
        ->toContain('author?: UserResource')
        ->toContain('post?: PostResource')
        ->not->toContain('@tolki/enum');
});

test('generates OrderResource with conditional methods', function () {
    config()->set('ts-publish.output_to_files', false);

    $generator = resolve(ResourceGenerator::class, ['findable' => OrderResource::class]);

    expect($generator->content)
        ->toContain("import { type AsEnum } from '@tolki/enum'")
        ->toContain("import { Currency, OrderStatus } from '../enums'")
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
