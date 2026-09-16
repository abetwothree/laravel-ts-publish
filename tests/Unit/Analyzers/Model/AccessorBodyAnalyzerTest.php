<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\MethodReturnTypeResolver;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\FilteringAccessorModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\UntypedFilterOverrideModel;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Release;
use Workbench\App\Models\User;

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

describe('AccessorBodyAnalyzer for a getter a method body reads without imports', function () {
    $published = [
        'own_picks' => ["{ v: Pick<FilteringAccessorModel, 'id' | 'title'>; id: number }", [FilteringAccessorModel::class]],
        'own_runtime' => ['{ v: Record<string, unknown>; id: number }', []],
        'own_nullsafe' => ["{ v: Pick<FilteringAccessorModel, 'id' | 'content'>; id: number }", [FilteringAccessorModel::class]],
        'author_picks' => ["{ v: Pick<User, 'id' | 'role'> | null; id: number }", [User::class]],
        'comment_picks' => ['{ v: Comment[]; id: number }', [Comment::class]],
        'comment_list' => ['Comment[]', [Comment::class]],
        'tagged_fields' => ['{ id: number }[]', []],
        'doc_records' => ['Comment[]', [Comment::class]],
        'doc_records_nullsafe' => ['Comment[] | null', [Comment::class]],
        'doc_class_list' => ['Comment[]', [Comment::class]],
        'doc_keyed' => ['Comment[]', [Comment::class]],
        'doc_int_mixed' => ['Comment[]', [Comment::class]],
        'doc_record_or_list' => ['Comment[]', [Comment::class]],
        'doc_nested_records' => ['Comment[][]', []],
        'signed_tag_rows' => ['Comment[]', [Comment::class]],
        'legacy_tag_rows' => ['Comment[]', [Comment::class]],
        'loose_picks' => ["{ v: Pick<UntypedFilterOverrideModel, 'id' | 'title'>; id: number }", [UntypedFilterOverrideModel::class]],
        'counterpart_picks' => ["{ v: Pick<Comment, 'id'> | Pick<User, 'id'> | null; id: number }", [Comment::class, User::class]],
        'loop_a' => ["{ v: { v: unknown; p: Pick<User, 'id'> }; p: Pick<User, 'id'> }", [User::class]],
    ];

    $readers = [
        'readOwnPicks', 'readOwnRuntime', 'readOwnNullsafe', 'readAuthorPicks', 'readCommentPicks', 'readCommentList',
        'readTaggedFields', 'readLoosePicks', 'readCounterpartPicks', 'readLoop', 'readDocRecords', 'readDocRecordsNullsafe',
        'readDocClassList', 'readSignedTagRows', 'readLegacyTagRows', 'readDocKeyed', 'readDocIntMixed', 'readDocRecordOrList',
        'readDocNestedRecords',
    ];

    // The model file publishes the analysis that keeps imports; a method body's import-less read must not replace it.
    test('the accessor keeps its published type and imports after a method body reads it', function () use ($published, $readers) {
        foreach ($readers as $reader) {
            resolve(MethodReturnTypeResolver::class)->resolve(FilteringAccessorModel::class, $reader);
        }

        $mutators = (new ModelTransformer(FilteringAccessorModel::class))->data()->mutators;

        foreach ($published as $attribute => [$type, $classFqcns]) {
            $resolved = resolve(ModelAttributeResolver::class)->resolveAttribute(FilteringAccessorModel::class, $attribute);

            expect($resolved['type'])->toBe($type)
                ->and($resolved['classFqcns'])->toBe($classFqcns)
                ->and($mutators[$attribute]['type'])->toBe($type);
        }
    });

    test('a method body reads the accessor without imports after the model publishes it', function () use ($published) {
        foreach (array_keys($published) as $attribute) {
            resolve(ModelAttributeResolver::class)->resolveAttribute(FilteringAccessorModel::class, $attribute);
        }

        expect(resolve(MethodReturnTypeResolver::class)->resolve(FilteringAccessorModel::class, 'readAuthorPicks')['type'] ?? null)
            ->toBe('{ v: { v: { id: number; role: unknown } | null; id: number }; id: number }')
            ->and(resolve(MethodReturnTypeResolver::class)->resolve(FilteringAccessorModel::class, 'readCommentList')['type'] ?? null)
            ->toBe('{ v: unknown[]; id: number }');
    });

    test('a cycle between two accessors terminates when a method body reads it without imports', function () {
        expect(resolve(MethodReturnTypeResolver::class)->resolve(FilteringAccessorModel::class, 'readLoop')['type'] ?? null)
            ->toBe('{ v: { v: { v: unknown; p: { id: number } }; p: { id: number } }; id: number }');
    });

    // The getter calls report(), whose body reads the getter back without imports: a guard shared by both modes would
    // cut that read short while the getter's own analysis is on the stack, so the answer would depend on who asked first.
    test('a getter being analyzed with imports does not cut short its own read without them', function (bool $getterFirst) {
        $getter = fn () => resolve(ModelAttributeResolver::class)->resolveAttribute(FilteringAccessorModel::class, 'self_report')['type'];
        $method = fn () => resolve(MethodReturnTypeResolver::class)->resolve(FilteringAccessorModel::class, 'report')['type'] ?? null;
        $report = '{ self: { v: { id: number }; report: unknown[] }; id: number }';

        [$first, $second] = $getterFirst ? [$getter(), $method()] : [$method(), $getter()];

        expect($getterFirst ? $first : $second)->toBe("{ v: Pick<User, 'id'>; report: $report }")
            ->and($getterFirst ? $second : $first)->toBe($report);
    })->with(['getter first' => true, 'method first' => false]);
});
