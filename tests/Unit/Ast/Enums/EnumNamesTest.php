<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Enums\EnumNames;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\UiTone;
use Workbench\Crm\Enums\Status as CrmStatus;
use Workbench\Shipping\Enums\Status as ShipmentStatus;

describe('EnumNames::typeName()', function () {
    test('names an enum by the type its file exports', function () {
        expect(EnumNames::typeName(Status::class))->toBe('StatusType')
            ->and(EnumNames::typeName(CrmStatus::class))->toBe('StatusType')
            ->and(EnumNames::typeName(ShipmentStatus::class))->toBe('ShipmentStatusType');
    });
});

describe('EnumNames::readsType()', function () {
    test("tells a value spelling its enum's type from one of an overridden enum, which spells the override", function () {
        expect(EnumNames::readsType(['type' => 'StatusType | null', 'optional' => false, 'directEnumFqcn' => Status::class]))
            ->toBeTrue()
            ->and(EnumNames::readsType(['type' => 'UiToneDto', 'optional' => false, 'directEnumFqcn' => UiTone::class]))
            ->toBeFalse()
            ->and(EnumNames::readsType(['type' => 'StatusType', 'optional' => false]))->toBeFalse();
    });
});
