<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Transformers\ClassTypeTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\ClassTypeWriter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Workbench\App\Packages\Ui\Dial;
use Workbench\App\Packages\Ui\Panel;

describe('ClassTypeWriter', function () {
    it('renders a composed class module from a transformer', function () {
        config()->set('ts-publish.output_to_files', false);

        $transformer = new ClassTypeTransformer(Dial::class);

        expect(new ClassTypeWriter(new Filesystem)->write($transformer))->toBe(<<<'TS'
            import type { UiDial, UiNode } from '@js/types/ui';

            export interface DialType extends UiDial, UiNode {}

            TS)
            ->and($transformer->filename())->toBe('dial')
            ->and($transformer->namespacePath)->toBe('workbench/app/packages/ui')
            ->and($transformer->typeName)->toBe('DialType')
            ->and($transformer->extends)->toBe(['UiDial', 'UiNode']);
    });

    it('renders with the default template when the template key is unset', function () {
        config()->set('ts-publish.output_to_files', false);
        config()->set('ts-publish.class_types', Arr::except(config()->array('ts-publish.class_types'), 'template'));

        expect(new ClassTypeWriter(new Filesystem)->write(new ClassTypeTransformer(Dial::class)))
            ->toContain('export interface DialType extends UiDial, UiNode {}');
    });

    it('writes the module into its namespace directory under the output directory', function () {
        $out = sys_get_temp_dir().'/ts-publish-class-type-writer-'.uniqid();
        config()->set('ts-publish.output_to_files', true);
        config()->set('ts-publish.output_directory', $out);

        try {
            $content = new ClassTypeWriter(new Filesystem)->write(new ClassTypeTransformer(Dial::class));

            expect(file_get_contents("$out/workbench/app/packages/ui/dial.ts"))->toBe($content);
        } finally {
            (new Filesystem)->deleteDirectory($out);
        }
    });

    it('refuses a class whose override does not compose', function () {
        new ClassTypeTransformer(Panel::class);
    })->throws(InvalidArgumentException::class, 'Workbench\App\Packages\Ui\Panel has no composed #[TsType]');
});
