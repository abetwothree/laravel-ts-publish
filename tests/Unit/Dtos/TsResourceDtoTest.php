<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Dtos\TsResourceDto;

describe('TsResourceDto', function () {
    beforeEach(function () {
        $this->dto = new TsResourceDto(
            resourceName: 'PostResource',
            description: 'A test resource',
            fqcn: 'App\Http\Resources\PostResource',
            filePath: 'app/Http/Resources/PostResource.php',
            filename: 'post-resource',
            properties: [
                'id' => ['type' => 'number', 'optional' => false, 'description' => ''],
                'title' => ['type' => 'string', 'optional' => false, 'description' => ''],
            ],
            typeImports: [
                '../enums' => ['StatusType'],
            ],
            valueImports: [
                '../enums' => ['Status'],
            ],
            modelClass: 'App\Models\Post',
        );
    });

    test('toArray returns all properties as array', function () {
        $array = $this->dto->toArray();

        expect($array)
            ->toBeArray()
            ->toHaveKey('resourceName', 'PostResource')
            ->toHaveKey('description', 'A test resource')
            ->toHaveKey('filePath', 'app/Http/Resources/PostResource.php')
            ->toHaveKey('filename', 'post-resource')
            ->and($array['properties'])->toHaveCount(2)
            ->and($array['typeImports'])->toHaveKey('../enums')
            ->and($array['valueImports'])->toHaveKey('../enums')
            ->and($array['modelClass'])->toBe('App\Models\Post');
    });

    test('toJson returns valid JSON string', function () {
        $json = $this->dto->toJson();

        expect($json)->toBeString()
            ->and(json_decode($json, true))->toBeArray()
            ->and(json_decode($json, true)['resourceName'])->toBe('PostResource');
    });

    test('jsonSerialize returns the same as toArray', function () {
        expect($this->dto->jsonSerialize())->toBe($this->dto->toArray());
    });
});
