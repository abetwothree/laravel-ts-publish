<?php

use AbeTwoThree\LaravelTsPublish\Transformers\EnumTransformer;
use Workbench\App\Enums\Color;
use Workbench\App\Enums\MembershipLevel;
use Workbench\App\Enums\PaymentMethod;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\Shipping\Enums\Status as ShippingStatus;

describe('EnumTransformer with backed int enum', function () {
    test('transforms a backed int enum', function () {
        $transformer = new EnumTransformer(Status::class);
        $data = $transformer->data();

        expect($data['enumName'])->toBe('Status')
            ->and($data['backed'])->toBeTrue()
            ->and($data['cases'])->toHaveCount(2)
            ->and($data['cases'][0])->toBe(['name' => 'Draft', 'value' => 0, 'description' => ''])
            ->and($data['cases'][1])->toBe(['name' => 'Published', 'value' => 1, 'description' => '']);
    });

    test('transforms Status enum methods', function () {
        $transformer = new EnumTransformer(Status::class);
        $data = $transformer->data();

        expect($data['methods'])
            ->toHaveKey('icon')
            ->toHaveKey('color')
            ->and($data['methods']['icon']['returns'])->toBe([
                'Draft' => 'pencil',
                'Published' => 'check',
            ])
            ->and($data['methods']['color']['returns'])->toBe([
                'Draft' => 'gray',
                'Published' => 'green',
            ]);
    });

    test('transforms Status enum method descriptions', function () {
        $transformer = new EnumTransformer(Status::class);
        $data = $transformer->data();

        expect($data['methods']['icon']['description'])->toBe('Get the icon name for the status')
            ->and($data['methods']['color']['description'])->toBe('');
    });

    test('transforms Status static methods', function () {
        $transformer = new EnumTransformer(Status::class);
        $data = $transformer->data();

        expect($data['staticMethods'])
            ->toHaveKey('valueLabelPair')
            ->toHaveKey('names')
            ->toHaveKey('values')
            ->toHaveKey('options');

        expect($data['staticMethods']['valueLabelPair']['description'])->toBe('Get the key-value pair options for the status');
        expect($data['staticMethods']['names']['return'])->toBe(['Draft', 'Published']);
        expect($data['staticMethods']['values']['return'])->toBe([0, 1]);
    });

    test('generates caseKinds for backed int enum', function () {
        $transformer = new EnumTransformer(Status::class);
        $data = $transformer->data();

        expect($data['caseKinds'])->toBe(["'Draft'", "'Published'"]);
    });

    test('generates caseTypes for backed int enum', function () {
        $transformer = new EnumTransformer(Status::class);
        $data = $transformer->data();

        expect($data['caseTypes'])->toBe([0, 1]);
    });
});

describe('EnumTransformer with backed string enum and TsCase overrides', function () {
    test('transforms Color enum with TsCase overrides', function () {
        $transformer = new EnumTransformer(Color::class);
        $data = $transformer->data();

        expect($data['enumName'])->toBe('Color')
            ->and($data['backed'])->toBeTrue()
            ->and($data['cases'])->toHaveCount(6);

        // Cases with only description overrides keep original name/value
        expect($data['cases'][0])->toBe(['name' => 'Red', 'value' => 'red', 'description' => 'Primary red color']);
        expect($data['cases'][1])->toBe(['name' => 'Green', 'value' => 'green', 'description' => 'Primary green color']);

        // Amber has full name/value/description override
        expect($data['cases'][3])->toBe(['name' => 'Yellow', 'value' => 'yellow', 'description' => 'Warning yellow']);

        // Gray has name/value override without description
        expect($data['cases'][4])->toBe(['name' => 'Slate', 'value' => 'slate', 'description' => '']);

        // Purple has no TsCase attribute
        expect($data['cases'][5])->toBe(['name' => 'Purple', 'value' => 'purple', 'description' => '']);
    });

    test('Color enum methods return correct values', function () {
        $transformer = new EnumTransformer(Color::class);
        $data = $transformer->data();

        expect($data['methods'])->toHaveKey('hex')->toHaveKey('rgb')
            ->and($data['methods']['hex']['returns']['Red'])->toBe('#EF4444')
            ->and($data['methods']['rgb']['returns']['Blue'])->toBe([59, 130, 246]);
    });

    test('generates caseTypes for backed string enum', function () {
        $transformer = new EnumTransformer(Color::class);
        $data = $transformer->data();

        expect($data['caseTypes'])->toContain("'red'", "'green'", "'blue'");
    });
});

describe('EnumTransformer with unit enum', function () {
    test('transforms a unit enum', function () {
        $transformer = new EnumTransformer(Role::class);
        $data = $transformer->data();

        expect($data['enumName'])->toBe('Role')
            ->and($data['backed'])->toBeFalse()
            ->and($data['cases'])->toHaveCount(3)
            ->and($data['cases'][0])->toBe(['name' => 'Admin', 'value' => 'Admin', 'description' => ''])
            ->and($data['methods'])->toBeEmpty()
            ->and($data['staticMethods'])->toBeEmpty();
    });

    test('unit enum has empty caseKinds', function () {
        $transformer = new EnumTransformer(Role::class);
        $data = $transformer->data();

        expect($data['caseKinds'])->toBeEmpty();
    });

    test('unit enum caseTypes use case names as strings', function () {
        $transformer = new EnumTransformer(Role::class);
        $data = $transformer->data();

        expect($data['caseTypes'])->toBe(["'Admin'", "'User'", "'Guest'"]);
    });
});

describe('EnumTransformer with minimal backed enum', function () {
    test('transforms a simple backed enum with no attributes', function () {
        $transformer = new EnumTransformer(PaymentMethod::class);
        $data = $transformer->data();

        expect($data['enumName'])->toBe('PaymentMethod')
            ->and($data['backed'])->toBeTrue()
            ->and($data['cases'])->toHaveCount(8)
            ->and($data['methods'])->toBeEmpty()
            ->and($data['staticMethods'])->toBeEmpty();
    });
});

describe('EnumTransformer with minimal unit enum', function () {
    test('transforms a minimal unit enum', function () {
        $transformer = new EnumTransformer(MembershipLevel::class);
        $data = $transformer->data();

        expect($data['enumName'])->toBe('MembershipLevel')
            ->and($data['backed'])->toBeFalse()
            ->and($data['methods'])->toBeEmpty()
            ->and($data['staticMethods'])->toBeEmpty();
    });
});

describe('EnumTransformer filename generation', function () {
    test('filename returns kebab-cased enum name', function () {
        expect((new EnumTransformer(Status::class))->filename())->toBe('status');
        expect((new EnumTransformer(PaymentMethod::class))->filename())->toBe('payment-method');
        expect((new EnumTransformer(MembershipLevel::class))->filename())->toBe('membership-level');
    });
});

describe('EnumTransformer filePath generation', function () {
    test('filePath is set to a relative path', function () {
        $transformer = new EnumTransformer(Status::class);
        $data = $transformer->data();

        expect($data['filePath'])->toContain('Enums')
            ->and($data['filePath'])->toContain('Status.php')
            ->and($data['filePath'])->not->toStartWith('/');
    });
});

describe('EnumTransformer with Priority enum that has methods throwing exceptions when invoked', function () {
    test('transforms Priority enum method that throws gracefully', function () {
        $transformer = new EnumTransformer(Priority::class);
        $data = $transformer->data();

        // isAboveThreshold requires a parameter — invoke without one triggers the catch block
        expect($data['methods'])->toHaveKey('isAboveThreshold')
            ->and($data['methods']['isAboveThreshold']['description'])->toBe('Compare with threshold');

        // All case returns should be null (caught exception)
        foreach ($data['methods']['isAboveThreshold']['returns'] as $caseName => $value) {
            expect($value)->toBeNull("Expected null for $caseName due to invocation error");
        }
    });

    test('transforms Priority enum static method that throws gracefully', function () {
        $transformer = new EnumTransformer(Priority::class);
        $data = $transformer->data();

        // filterByMinimum requires a parameter — invoke without one triggers the catch block
        expect($data['staticMethods'])->toHaveKey('filterByMinimum')
            ->and($data['staticMethods']['filterByMinimum']['description'])->toBe('Filter by minimum')
            ->and($data['staticMethods']['filterByMinimum']['return'])->toBeNull();
    });
});

describe('EnumTransformer with TsEnum attribute', function () {
    test('transforms enum with TsEnum attribute using custom name', function () {
        $transformer = new EnumTransformer(ShippingStatus::class);
        $data = $transformer->data();

        expect($data['enumName'])->toBe('ShipmentStatus')
            ->and($data['backed'])->toBeTrue()
            ->and($data['cases'])->toHaveCount(7)
            ->and($data['cases'][0]['name'])->toBe('Pending')
            ->and($data['cases'][0]['value'])->toBe('pending');
    });

    test('TsEnum attribute overrides filename to kebab-cased custom name', function () {
        $transformer = new EnumTransformer(ShippingStatus::class);

        expect($transformer->filename())->toBe('shipment-status');
    });

    test('enum without TsEnum attribute uses default short name', function () {
        $transformer = new EnumTransformer(Status::class);
        $data = $transformer->data();

        expect($data['enumName'])->toBe('Status')
            ->and($transformer->filename())->toBe('status');
    });
});
