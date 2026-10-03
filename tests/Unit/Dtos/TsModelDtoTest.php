<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Dtos\TsModelDto;

describe('TsModelDto', function () {
    beforeEach(function () {
        $this->dto = new TsModelDto(
            modelName: 'User',
            description: 'A test model',
            fqcn: 'App\Models\User',
            filePath: 'app/Models/User.php',
            filename: 'user',
            columns: [
                'id' => ['type' => 'number', 'description' => ''],
                'name' => ['type' => 'string', 'description' => ''],
            ],
            mutators: [
                'initials' => ['type' => 'string', 'description' => ''],
            ],
            appends: [
                'full_name' => ['type' => 'string', 'description' => ''],
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
                'status' => ['constName' => 'Status', 'nullable' => false, 'isCollection' => false],
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
            ->and($array['appends'])->toHaveKey('full_name')
            ->and($array['relations'])->toHaveKey('posts')
            ->and($array['typeImports'])->toHaveKey('../enums')
            ->and($array['valueImports'])->toHaveKey('../enums')
            ->and($array['enumColumns'])->toHaveKey('status')
            ->and($array['enumMutators'])->toBeEmpty()
            ->and($array['enumAppends'])->toBeEmpty();
    });

    test('toArray carries the combined views beside the full lists', function () {
        $status = ['constName' => 'Status', 'nullable' => false, 'isCollection' => false];

        $array = (new TsModelDto(
            modelName: 'Depot',
            description: '',
            fqcn: 'App\Models\Depot',
            filePath: 'app/Models/Depot.php',
            filename: 'depot',
            columns: [
                'id' => ['type' => 'number', 'description' => '', 'optional' => false],
                'supervisor' => ['type' => 'StatusType', 'description' => '', 'optional' => false],
            ],
            mutators: [],
            appends: [],
            relations: ['supervisor' => ['type' => 'User', 'description' => '']],
            typeImports: ['../enums' => ['StatusType'], '.' => ['User']],
            valueImports: ['../enums' => ['Status']],
            enumColumns: ['supervisor' => $status],
            shadowedKeys: ['supervisor'],
            combinedColumns: ['id' => ['type' => 'number', 'description' => '', 'optional' => false]],
            combinedEnums: [],
            combinedTypeImports: ['.' => ['User']],
            combinedValueImports: [],
        ))->toArray();

        expect(array_keys($array['columns']))->toBe(['id', 'supervisor'])
            ->and(array_keys($array['combinedColumns']))->toBe(['id'])
            ->and($array['combinedMutators'])->toBe([])
            ->and($array['combinedAppends'])->toBe([])
            ->and($array['enumColumns'])->toBe(['supervisor' => $status])
            ->and($array['combinedEnums'])->toBe([])
            ->and($array['typeImports'])->toBe(['../enums' => ['StatusType'], '.' => ['User']])
            ->and($array['combinedTypeImports'])->toBe(['.' => ['User']])
            ->and($array['valueImports'])->toBe(['../enums' => ['Status']])
            ->and($array['combinedValueImports'])->toBe([]);
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

    test('withoutShadowedKeys wraps an interface in Omit only for the keys it shares with a relation', function () {
        $dto = new TsModelDto(
            modelName: 'Depot',
            description: '',
            fqcn: 'App\Models\Depot',
            filePath: 'app/Models/Depot.php',
            filename: 'depot',
            columns: [],
            mutators: [],
            appends: [],
            relations: [],
            typeImports: [],
            shadowedKeys: ['supervisor', 'region'],
        );

        expect($dto->withoutShadowedKeys('Depot', ['id', 'supervisor']))->toBe("Omit<Depot, 'supervisor'>")
            ->and($dto->withoutShadowedKeys('Depot', ['supervisor', 'region']))->toBe("Omit<Depot, 'supervisor' | 'region'>")
            ->and($dto->withoutShadowedKeys('DepotMutators', ['label']))->toBe('DepotMutators');
    });
});
