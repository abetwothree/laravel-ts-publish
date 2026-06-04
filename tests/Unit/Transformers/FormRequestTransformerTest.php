<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Transformers\FormRequestTransformer;
use Workbench\App\Http\Requests\DynamicRequest;
use Workbench\App\Http\Requests\StorePostRequest;

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
