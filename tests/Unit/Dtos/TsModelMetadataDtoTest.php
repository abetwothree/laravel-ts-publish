<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Dtos\TsModelMetadataDto;

test('converts model metadata to array and JSON', function () {
    $dto = new TsModelMetadataDto(
        modelName: 'User',
        filename: 'user_meta',
        properties: ['morphClass' => 'user'],
        propertyTypes: ['morphClass' => 'string'],
        typeImports: ['@/types/model-metadata' => ['ModelMetadataDetails']],
    );

    expect($dto->toArray())
        ->toBe([
            'modelName' => 'User',
            'filename' => 'user_meta',
            'properties' => ['morphClass' => 'user'],
            'propertyTypes' => ['morphClass' => 'string'],
            'typeImports' => ['@/types/model-metadata' => ['ModelMetadataDetails']],
        ])
        ->and($dto->toJson())->toBeJson()
        ->and($dto->jsonSerialize())->toBe($dto->toArray());
});
