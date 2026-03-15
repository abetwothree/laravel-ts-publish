<?php

use AbeTwoThree\LaravelTsPublish\Dtos\TsModelDto;

describe('TsModelDto', function () {
    beforeEach(function () {
        $this->dto = new TsModelDto(
            modelName: 'User',
            description: 'A test model',
            filePath: 'app/Models/User.php',
            filename: 'user',
            columns: [
                'id' => ['type' => 'number', 'description' => ''],
                'name' => ['type' => 'string', 'description' => ''],
            ],
            mutators: [
                'initials' => ['type' => 'string', 'description' => ''],
            ],
            relations: [
                'posts' => ['type' => 'Post[]', 'description' => ''],
            ],
            typeImports: [
                '../enums' => ['StatusType'],
            ],
            valueImports: [
                '../enums' => ['Status'],
            ],
            enumColumns: [
                'status' => ['constName' => 'Status', 'nullable' => false],
            ],
            enumMutators: [],
        );
    });

    test('toArray returns all properties as array', function () {
        $array = $this->dto->toArray();

        expect($array)
            ->toBeArray()
            ->toHaveKey('modelName', 'User')
            ->toHaveKey('description', 'A test model')
            ->toHaveKey('filePath', 'app/Models/User.php')
            ->toHaveKey('filename', 'user')
            ->and($array['columns'])->toHaveCount(2)
            ->and($array['mutators'])->toHaveKey('initials')
            ->and($array['relations'])->toHaveKey('posts')
            ->and($array['typeImports'])->toHaveKey('../enums')
            ->and($array['valueImports'])->toHaveKey('../enums')
            ->and($array['enumColumns'])->toHaveKey('status')
            ->and($array['enumMutators'])->toBeEmpty();
    });

    test('toJson returns valid JSON string', function () {
        $json = $this->dto->toJson();

        expect($json)->toBeString()
            ->and(json_decode($json, true))->toBeArray()
            ->and(json_decode($json, true)['modelName'])->toBe('User');
    });

    test('jsonSerialize returns the same as toArray', function () {
        expect($this->dto->jsonSerialize())->toBe($this->dto->toArray());
    });
});
