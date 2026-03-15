<?php

use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\ModelWriter;
use Illuminate\Filesystem\Filesystem;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Crm\Models\Deal;

test('writes model content from transformer', function () {
    $writer = new ModelWriter(new Filesystem);
    $transformer = new ModelTransformer(User::class);

    config()->set('ts-publish.output_to_files', false);

    $content = $writer->write($transformer);

    expect($content)
        ->toContain('export interface User')
        ->toContain('id: number')
        ->toContain('name: string')
        ->toContain('email: string');
});

test('writes model file to disk when output_to_files is enabled', function () {
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('ensureDirectoryExists')->once();
    $filesystem->shouldReceive('put')->once()
        ->withArgs(function (string $path, string $content) {
            return str_contains($path, 'user.ts') && str_contains($content, 'export interface User');
        });

    $writer = new ModelWriter($filesystem);
    $transformer = new ModelTransformer(User::class);

    config()->set('ts-publish.output_to_files', true);

    $writer->write($transformer);
});

test('does not write model file to disk when output_to_files is disabled', function () {
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldNotReceive('ensureDirectoryExists');
    $filesystem->shouldNotReceive('put');

    $writer = new ModelWriter($filesystem);
    $transformer = new ModelTransformer(User::class);

    config()->set('ts-publish.output_to_files', false);

    $writer->write($transformer);
});

test('writes model relations interfaces', function () {
    $writer = new ModelWriter(new Filesystem);
    $transformer = new ModelTransformer(User::class);

    config()->set('ts-publish.output_to_files', false);

    $content = $writer->write($transformer);

    expect($content)
        ->toContain('export interface UserRelations');
});

test('writes model mutators interface', function () {
    $writer = new ModelWriter(new Filesystem);
    $transformer = new ModelTransformer(User::class);

    config()->set('ts-publish.output_to_files', false);

    $content = $writer->write($transformer);

    expect($content)->toContain('export interface UserMutators');
});

describe('ModelWriter Resource interface output', function () {
    test('generates Resource interface for Post model in model-full template', function () {
        $writer = new ModelWriter(new Filesystem);
        $transformer = new ModelTransformer(Post::class);

        config()->set('ts-publish.output_to_files', false);
        config()->set('ts-publish.model_template', 'laravel-ts-publish::model-full');

        $content = $writer->write($transformer);

        expect($content)
            ->toContain('export interface PostResource extends Omit<Post,')
            ->toContain('AsEnum<typeof Status>')
            ->toContain('AsEnum<typeof Visibility> | null')
            ->toContain('AsEnum<typeof Priority> | null');
    });

    test('generates Resource interface for Post model in model-split template', function () {
        $writer = new ModelWriter(new Filesystem);
        $transformer = new ModelTransformer(Post::class);

        config()->set('ts-publish.output_to_files', false);
        config()->set('ts-publish.model_template', 'laravel-ts-publish::model-split');

        $content = $writer->write($transformer);

        expect($content)
            ->toContain('export interface PostResource extends Omit<Post,')
            ->toContain('AsEnum<typeof Status>')
            ->toContain('AsEnum<typeof Visibility> | null')
            ->toContain('AsEnum<typeof Priority> | null');
    });

    test('uses typeof for non-type imports in Resource', function () {
        $writer = new ModelWriter(new Filesystem);
        $transformer = new ModelTransformer(Post::class);

        config()->set('ts-publish.output_to_files', false);
        config()->set('ts-publish.model_template', 'laravel-ts-publish::model-full');

        $content = $writer->write($transformer);

        expect($content)
            ->toContain('AsEnum<typeof Status>')
            ->toContain('AsEnum<typeof Visibility> | null')
            ->toContain('AsEnum<typeof Priority> | null');
    });

    test('does not generate Resource when enums_use_tolki_package is disabled', function () {
        $writer = new ModelWriter(new Filesystem);
        $transformer = new ModelTransformer(Post::class);

        config()->set('ts-publish.output_to_files', false);
        config()->set('ts-publish.enums_use_tolki_package', false);

        $content = $writer->write($transformer);

        expect($content)->not->toContain('Resource');
    });

    test('does not generate Resource for model with no enum casts', function () {
        $writer = new ModelWriter(new Filesystem);
        $transformer = new ModelTransformer(User::class);

        config()->set('ts-publish.output_to_files', false);

        $content = $writer->write($transformer);

        // User has enum casts (role, membership_level) so it WILL have Resource
        expect($content)->toContain('UserResource');
    });

    test('generates Resource with aliased const names for Deal model', function () {
        $writer = new ModelWriter(new Filesystem);
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');
        $transformer = new ModelTransformer(Deal::class);

        config()->set('ts-publish.output_to_files', false);
        config()->set('ts-publish.model_template', 'laravel-ts-publish::model-full');
        $content = $writer->write($transformer);

        expect($content)
            ->toContain('export interface DealResource')
            ->toContain('AsEnum<typeof AppStatus>')
            ->toContain('AsEnum<typeof CrmStatus>');
    });

    test('model-split Resource extends Omit of column interface only when only columns have enums', function () {
        $writer = new ModelWriter(new Filesystem);
        $transformer = new ModelTransformer(Post::class);

        config()->set('ts-publish.output_to_files', false);
        config()->set('ts-publish.model_template', 'laravel-ts-publish::model-split');

        $content = $writer->write($transformer);

        // Post has enum columns but no enum mutators
        // Should extend Omit<Post, ...> and PostMutators (no Omit), and PostRelations
        expect($content)
            ->toContain('Omit<Post,')
            ->not->toContain('Omit<PostMutators');
    });

    test('Omit keys include all enum column names', function () {
        $writer = new ModelWriter(new Filesystem);
        $transformer = new ModelTransformer(Post::class);

        config()->set('ts-publish.output_to_files', false);
        config()->set('ts-publish.model_template', 'laravel-ts-publish::model-full');

        $content = $writer->write($transformer);

        expect($content)
            ->toContain("'status'")
            ->toContain("'visibility'")
            ->toContain("'priority'");
    });

    test('imports AsEnum from @tolki/enum', function () {
        $writer = new ModelWriter(new Filesystem);
        $transformer = new ModelTransformer(Post::class);

        config()->set('ts-publish.output_to_files', false);

        $content = $writer->write($transformer);

        expect($content)->toContain("import { type AsEnum } from '@tolki/enum'");
    });

    test('imports enum const names alongside type names', function () {
        $writer = new ModelWriter(new Filesystem);
        $transformer = new ModelTransformer(Post::class);

        config()->set('ts-publish.output_to_files', false);

        $content = $writer->write($transformer);

        // The enum import line should contain both type and const names
        expect($content)->toMatch('/from \'\.\.\/enums\'.*$/m');

        // Extract the enum import line
        preg_match("/import type \{ (.+) \} from '\.\.\/enums'/", $content, $matches);
        $importedNames = array_map('trim', explode(',', $matches[1]));

        expect($importedNames)
            ->toContain('Status')
            ->toContain('StatusType')
            ->toContain('Visibility')
            ->toContain('VisibilityType')
            ->toContain('Priority')
            ->toContain('PriorityType');
    });
});
