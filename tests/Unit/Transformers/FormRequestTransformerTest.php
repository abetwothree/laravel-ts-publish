<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Dtos\TsFormRequestDto;
use AbeTwoThree\LaravelTsPublish\Transformers\FormRequestTransformer;
use Workbench\App\Http\Requests\DynamicRequest;
use Workbench\App\Http\Requests\StorePostRequest;
use Workbench\App\Http\Requests\UpdatePostRequest;

describe('FormRequestTransformer', function () {
    it('sets typeName from the short class name', function () {
        $transformer = new FormRequestTransformer(StorePostRequest::class);

        expect($transformer->typeName)->toBe('StorePostRequest');
    });

    it('sets filename as kebab-case', function () {
        $transformer = new FormRequestTransformer(StorePostRequest::class);

        expect($transformer->filename())->toBe('store-post-request');
    });

    it('sets namespacePath', function () {
        $transformer = new FormRequestTransformer(StorePostRequest::class);

        expect($transformer->namespacePath)->not->toBeEmpty();
        expect($transformer->namespacePath)->toContain('requests');
    });

    it('populates fields for static requests', function () {
        $transformer = new FormRequestTransformer(StorePostRequest::class);

        expect($transformer->isDynamic)->toBeFalse();
        expect($transformer->fields)->not->toBeEmpty();

        $fieldPaths = array_column($transformer->fields, 'fieldPath');
        expect($fieldPaths)->toContain('title');
        expect($fieldPaths)->toContain('body');
    });

    it('marks isDynamic for dynamic requests', function () {
        $transformer = new FormRequestTransformer(DynamicRequest::class);

        expect($transformer->isDynamic)->toBeTrue();
        expect($transformer->fields)->toBeEmpty();
    });

    it('returns a TsFormRequestDto from data()', function () {
        $transformer = new FormRequestTransformer(StorePostRequest::class);
        $dto = $transformer->data();

        expect($dto->typeName)->toBe('StorePostRequest');
        expect($dto->fqcn)->toBe(StorePostRequest::class);
        expect($dto->isDynamic)->toBeFalse();
        expect($dto->fields)->not->toBeEmpty();
    });
});

describe('TsCasts overrides', function () {
    describe('StorePostRequest (simple string overrides)', function () {
        it('applies #[TsCasts] string override for tags', function () {
            $transformer = new FormRequestTransformer(StorePostRequest::class);

            $field = collect($transformer->fields)->firstWhere('fieldPath', 'tags');
            expect($field)->not->toBeNull();
            expect($field['tsType'])->toBe('string[]');
        });

        it('applies #[TsCasts] string override for rating', function () {
            $transformer = new FormRequestTransformer(StorePostRequest::class);

            $field = collect($transformer->fields)->firstWhere('fieldPath', 'rating');
            expect($field)->not->toBeNull();
            expect($field['tsType'])->toBe('number | bigint');
        });

        it('does not add typeImports for plain string TsCasts overrides', function () {
            $transformer = new FormRequestTransformer(StorePostRequest::class);

            expect($transformer->typeImports)->toBeEmpty();
        });

        it('leaves non-overridden fields resolved by the rules analyzer', function () {
            $transformer = new FormRequestTransformer(StorePostRequest::class);

            $field = collect($transformer->fields)->firstWhere('fieldPath', 'title');
            expect($field)->not->toBeNull();
            expect($field['tsType'])->toBe('string');
        });

        it('passes typeImports through to the DTO', function () {
            $transformer = new FormRequestTransformer(StorePostRequest::class);
            $dto = $transformer->data();

            expect($dto)->toBeInstanceOf(TsFormRequestDto::class);
            expect($dto->typeImports)->toBeArray();
        });
    });

    describe('UpdatePostRequest (string override + import-path override)', function () {
        it('applies #[TsCasts] string override for status', function () {
            $transformer = new FormRequestTransformer(UpdatePostRequest::class);

            $field = collect($transformer->fields)->firstWhere('fieldPath', 'status');
            expect($field)->not->toBeNull();
            expect($field['tsType'])->toBe("'draft' | 'published' | 'archived'");
        });

        it('applies #[TsCasts] array override for attributes', function () {
            $transformer = new FormRequestTransformer(UpdatePostRequest::class);

            $field = collect($transformer->fields)->firstWhere('fieldPath', 'attributes');
            expect($field)->not->toBeNull();
            expect($field['tsType'])->toBe('PostAttributes');
        });

        it('adds the declared import path to typeImports', function () {
            $transformer = new FormRequestTransformer(UpdatePostRequest::class);

            expect($transformer->typeImports)->toHaveKey('@js/types/posts');
            expect($transformer->typeImports['@js/types/posts'])->toContain('PostAttributes');
        });

        it('does not produce typeImports for the plain string override', function () {
            $transformer = new FormRequestTransformer(UpdatePostRequest::class);

            $allTypes = array_merge(...array_values($transformer->typeImports));
            expect($allTypes)->not->toContain("'draft' | 'published' | 'archived'");
        });

        it('leaves non-overridden fields resolved by the rules analyzer', function () {
            $transformer = new FormRequestTransformer(UpdatePostRequest::class);

            $field = collect($transformer->fields)->firstWhere('fieldPath', 'title');
            expect($field)->not->toBeNull();
            expect($field['tsType'])->toBe('string');
        });

        it('passes typeImports through to the DTO', function () {
            $transformer = new FormRequestTransformer(UpdatePostRequest::class);
            $dto = $transformer->data();

            expect($dto->typeImports)->toHaveKey('@js/types/posts');
        });
    });
});
