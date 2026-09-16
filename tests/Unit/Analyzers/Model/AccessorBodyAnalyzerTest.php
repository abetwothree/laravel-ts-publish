<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Workbench\App\Models\Release;

describe('AccessorBodyAnalyzer through ModelAttributeResolver', function () {
    test('types accessors from their getter bodies', function (string $attribute, string $expected) {
        expect(resolve(ModelAttributeResolver::class)->resolveAttribute(Release::class, $attribute)['type'])->toBe($expected);
    })->with([
        ['version_data', '{ major: number; minor: number }'],
        ['label', '{ full: string; notes: string | null }'],
        ['tag_list', '{ name: string }[]'],
        ['channels', 'string[]'],
        ['summary', '{ major: number }'],
        ['dynamic_totals', 'unknown[]'],
    ]);

    test('keys a constant-keyed getter body by the constant values', function () {
        expect(resolve(ModelAttributeResolver::class)->resolveAttribute(Release::class, 'channel_options')['type'])
            ->toBe('{ "1": string; "2": string }');
    });

    test('a cycle between two accessors terminates as unknown', function () {
        expect(resolve(ModelAttributeResolver::class)->resolveAttribute(Release::class, 'loop_a')['type'])->toBe('unknown');
    });
});
