<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Dtos\TsFormRequestDto;
use AbeTwoThree\LaravelTsPublish\Transformers\FormRequestTransformer;
use Workbench\App\Http\Requests\DynamicRequest;
use Workbench\App\Http\Requests\NumberRulesRequest;
use Workbench\App\Http\Requests\StorePostRequest;
use Workbench\App\Http\Requests\StringRulesRequest;
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

describe('TsExtends on FormRequestTransformer', function () {
    describe('StringRulesRequest (direct #[TsExtends] on class)', function () {
        it('stores the extends clause on the transformer', function () {
            $transformer = new FormRequestTransformer(StringRulesRequest::class);
            expect($transformer->tsExtends)->toBe(['FormRequestBase']);
        });

        it('adds the import path from the attribute to typeImports', function () {
            $transformer = new FormRequestTransformer(StringRulesRequest::class);
            expect($transformer->typeImports)->toHaveKey('@/types/requests');
            expect($transformer->typeImports['@/types/requests'])->toContain('FormRequestBase');
        });

        it('passes tsExtends through to the DTO', function () {
            $transformer = new FormRequestTransformer(StringRulesRequest::class);
            $dto = $transformer->data();
            expect($dto)->toBeInstanceOf(TsFormRequestDto::class);
            expect($dto->tsExtends)->toBe(['FormRequestBase']);
        });

        it('renders the extends clause in the blade template output', function () {
            $transformer = new FormRequestTransformer(StringRulesRequest::class);
            $output = view('laravel-ts-publish::form-request', ['data' => $transformer->data()])->render();
            expect($output)->toContain('export interface StringRulesRequest extends FormRequestBase {');
        });
    });

    describe('NumberRulesRequest (trait-based #[TsExtends])', function () {
        it('stores the extends clause propagated from the trait', function () {
            $transformer = new FormRequestTransformer(NumberRulesRequest::class);
            expect($transformer->tsExtends)->toBe(['HasValidationMeta']);
        });

        it('adds the import path from the trait attribute to typeImports', function () {
            $transformer = new FormRequestTransformer(NumberRulesRequest::class);
            expect($transformer->typeImports)->toHaveKey('@/types/validation');
            expect($transformer->typeImports['@/types/validation'])->toContain('HasValidationMeta');
        });

        it('passes tsExtends through to the DTO', function () {
            $transformer = new FormRequestTransformer(NumberRulesRequest::class);
            $dto = $transformer->data();
            expect($dto->tsExtends)->toBe(['HasValidationMeta']);
        });

        it('renders the extends clause from the trait in the blade output', function () {
            $transformer = new FormRequestTransformer(NumberRulesRequest::class);
            $output = view('laravel-ts-publish::form-request', ['data' => $transformer->data()])->render();
            expect($output)->toContain('export interface NumberRulesRequest extends HasValidationMeta {');
        });
    });

    describe('requests without #[TsExtends]', function () {
        it('has an empty tsExtends array', function () {
            $transformer = new FormRequestTransformer(StorePostRequest::class);
            expect($transformer->tsExtends)->toBeEmpty();
        });

        it('does not render an extends clause in the blade output', function () {
            $transformer = new FormRequestTransformer(StorePostRequest::class);
            $output = view('laravel-ts-publish::form-request', ['data' => $transformer->data()])->render();
            expect($output)->toContain('export interface StorePostRequest {');
            expect($output)->not->toContain('extends');
        });
    });

    describe('global config ts_extends.form_requests', function () {
        it('applies a globally configured extends clause to all form requests', function () {
            config(['ts-publish.ts_extends.form_requests' => ['GlobalFormBase']]);
            $transformer = new FormRequestTransformer(StorePostRequest::class);
            expect($transformer->tsExtends)->toContain('GlobalFormBase');
        });

        it('merges global config extends with class-level #[TsExtends]', function () {
            config(['ts-publish.ts_extends.form_requests' => ['GlobalFormBase']]);
            $transformer = new FormRequestTransformer(StringRulesRequest::class);
            expect($transformer->tsExtends)->toContain('GlobalFormBase');
            expect($transformer->tsExtends)->toContain('FormRequestBase');
        });
    });
});
