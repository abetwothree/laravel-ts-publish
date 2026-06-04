<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Transformers\FormRequestTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\FormRequestWriter;
use Illuminate\Filesystem\Filesystem;
use Workbench\App\Http\Requests\DynamicRequest;
use Workbench\App\Http\Requests\StorePostRequest;

describe('FormRequestWriter', function () {
    it('writes a static interface from a transformer', function () {
        config()->set('ts-publish.output_to_files', false);

        $writer = new FormRequestWriter(new Filesystem);
        $transformer = new FormRequestTransformer(StorePostRequest::class);

        $content = $writer->write($transformer);

        expect($content)
            ->toContain('export interface StorePostRequest')
            ->toContain('@see')
            ->not->toContain('@dynamic');
    });

    it('writes a dynamic type when isDynamic is true', function () {
        config()->set('ts-publish.output_to_files', false);

        $writer = new FormRequestWriter(new Filesystem);
        $transformer = new FormRequestTransformer(DynamicRequest::class);

        $content = $writer->write($transformer);

        expect($content)
            ->toContain('export type DynamicRequest')
            ->toContain('Record<string, unknown>')
            ->toContain('@dynamic');
    });

    it('includes field names in interface body', function () {
        config()->set('ts-publish.output_to_files', false);

        $writer = new FormRequestWriter(new Filesystem);
        $transformer = new FormRequestTransformer(StorePostRequest::class);

        $content = $writer->write($transformer);

        expect($content)
            ->toContain('title')
            ->toContain('body')
            ->toContain('string');
    });

    it('marks optional fields with ?', function () {
        config()->set('ts-publish.output_to_files', false);

        $writer = new FormRequestWriter(new Filesystem);
        $transformer = new FormRequestTransformer(StorePostRequest::class);

        $content = $writer->write($transformer);

        // 'published' has no required rule → should be optional
        expect($content)->toMatch('/published\?:/');
    });

    it('writes file to disk when output_to_files is enabled', function () {
        $filesystem = Mockery::mock(Filesystem::class);
        $filesystem->shouldReceive('ensureDirectoryExists')->once();
        $filesystem->shouldReceive('put')->once()
            ->withArgs(function (string $path, string $content) {
                return str_ends_with($path, 'store-post-request.ts')
                    && str_contains($content, 'StorePostRequest');
            });

        config()->set('ts-publish.output_to_files', true);
        config()->set('ts-publish.output_directory', '/tmp/ts-test');
        config()->set('ts-publish.form_requests.output_path', '');

        $writer = new FormRequestWriter($filesystem);
        $transformer = new FormRequestTransformer(StorePostRequest::class);

        $writer->write($transformer);
    });
});
