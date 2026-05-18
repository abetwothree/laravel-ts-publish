<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Dtos\TsEnumDto;

describe('TsEnumDto', function () {
    beforeEach(function () {
        $this->dto = new TsEnumDto(
            enumName: 'Status',
            description: 'A test enum',
            fqcn: 'App\Enums\Status',
            filePath: 'app/Enums/Status.php',
            filename: 'status',
            cases: [
                ['name' => 'Draft', 'value' => 0, 'description' => ''],
                ['name' => 'Published', 'value' => 1, 'description' => ''],
            ],
            methods: [
                'icon' => ['name' => 'icon', 'description' => 'The icon', 'returns' => ['Draft' => 'pencil']],
            ],
            staticMethods: [
                'names' => ['name' => 'names', 'description' => '', 'return' => ['Draft', 'Published']],
            ],
            caseKinds: ["'Draft'", "'Published'"],
            caseTypes: [0, 1],
            backed: true,
        );
    });

    test('toArray returns all properties as array', function () {
        $array = $this->dto->toArray();

        expect($array)
            ->toBeArray()
            ->toHaveKey('enumName', 'Status')
            ->toHaveKey('description', 'A test enum')
            ->toHaveKey('filePath', 'app/Enums/Status.php')
            ->toHaveKey('filename', 'status')
            ->toHaveKey('backed', true)
            ->and($array['cases'])->toHaveCount(2)
            ->and($array['methods'])->toHaveKey('icon')
            ->and($array['staticMethods'])->toHaveKey('names')
            ->and($array['caseKinds'])->toBe(["'Draft'", "'Published'"])
            ->and($array['caseTypes'])->toBe([0, 1]);
    });

    test('toJson returns valid JSON string', function () {
        $json = $this->dto->toJson();

        expect($json)->toBeString()
            ->and(json_decode($json, true))->toBeArray()
            ->and(json_decode($json, true)['enumName'])->toBe('Status');
    });

    test('jsonSerialize returns the same as toArray', function () {
        expect($this->dto->jsonSerialize())->toBe($this->dto->toArray());
    });
});
