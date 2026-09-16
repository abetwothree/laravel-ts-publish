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

    test('an empty literal body is declined, so the getter signature still answers', function () {
        // `[]` resolves to never[] — non-vague, but holding nothing. Publishing it would override a
        // better annotation, so the body step declines and the vague `: array` answers as before.
        expect(resolve(ModelAttributeResolver::class)->resolveAttribute(Release::class, 'empty_list')['type'])
            ->toBe('unknown[]');
    });

    test('reads the getter off the new Attribute(get: ...) constructor form', function () {
        expect(resolve(ModelAttributeResolver::class)->resolveAttribute(Release::class, 'constructed_version')['type'])
            ->toBe('{ major: number }');
    });

    test('a trait-declared accessor resolves $this against the model that uses the trait', function () {
        // The body lives in DerivesReleaseVersion's own file; `major` is a column on releases, so it
        // only resolves because analyzeModelClosure() puts the model class on the scope.
        expect(resolve(ModelAttributeResolver::class)->resolveAttribute(Release::class, 'trait_version')['type'])
            ->toBe('{ major: number; label: string }');
    });

    test('a cycle between two accessors terminates as unknown', function () {
        expect(resolve(ModelAttributeResolver::class)->resolveAttribute(Release::class, 'loop_a')['type'])->toBe('unknown');
    });
});
