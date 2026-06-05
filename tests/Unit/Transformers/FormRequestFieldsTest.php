<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Transformers\FormRequestTransformer;
use Workbench\App\Http\Requests\ArrayRulesRequest;
use Workbench\App\Http\Requests\BooleanRulesRequest;
use Workbench\App\Http\Requests\DatabaseRulesRequest;
use Workbench\App\Http\Requests\DateRulesRequest;
use Workbench\App\Http\Requests\DynamicRequest;
use Workbench\App\Http\Requests\FileRulesRequest;
use Workbench\App\Http\Requests\NumberRulesRequest;
use Workbench\App\Http\Requests\RuleClassRequest;
use Workbench\App\Http\Requests\StorePostRequest;
use Workbench\App\Http\Requests\StringRulesRequest;
use Workbench\App\Http\Requests\UpdatePostRequest;
use Workbench\App\Http\Requests\UtilityRulesRequest;

describe('StorePostRequest fields', function () {
    beforeEach(function () {
        $this->transformer = new FormRequestTransformer(StorePostRequest::class);
    });

    it('email field is required string with @format email', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'email');

        expect($field)->not->toBeNull()
            ->and($field['tsType'])->toBe('string')
            ->and($field['isRequired'])->toBeTrue()
            ->and($field['jsDocMetadata'])->toContain('@format email');
    });

    it('rating field is optional nullable number', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'rating');

        expect($field)->not->toBeNull()
            ->and($field['tsType'])->toBe('number')
            ->and($field['isRequired'])->toBeFalse()
            ->and($field['isNullable'])->toBeTrue();
    });

    it('tags field is optional string array', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'tags');

        expect($field)->not->toBeNull()
            ->and($field['tsType'])->toBe('string[]')
            ->and($field['isRequired'])->toBeFalse();
    });

    it('tags wildcard field is string', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'tags.*');

        expect($field)->not->toBeNull()
            ->and($field['tsType'])->toBe('string');
    });
});

describe('UpdatePostRequest fields', function () {
    beforeEach(function () {
        $this->transformer = new FormRequestTransformer(UpdatePostRequest::class);
    });

    it('title field is optional due to sometimes rule', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'title');

        expect($field)->not->toBeNull()
            ->and($field['tsType'])->toBe('string')
            ->and($field['isRequired'])->toBeFalse();
    });

    it('status field is required union type from Rule::in', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'status');

        expect($field)->not->toBeNull()
            ->and($field['isRequired'])->toBeTrue()
            ->and($field['tsType'])->toContain("'draft'")
            ->and($field['tsType'])->toContain('|');
    });

    it('category_id field is required number with @constraint exists', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'category_id');

        expect($field)->not->toBeNull()
            ->and($field['tsType'])->toBe('number')
            ->and($field['isRequired'])->toBeTrue()
            ->and($field['jsDocMetadata'])->toContain('@constraint exists');
    });

    it('priority field is optional nullable number', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'priority');

        expect($field)->not->toBeNull()
            ->and($field['tsType'])->toBe('number')
            ->and($field['isRequired'])->toBeFalse()
            ->and($field['isNullable'])->toBeTrue();
    });
});

describe('BooleanRulesRequest fields', function () {
    beforeEach(function () {
        $this->transformer = new FormRequestTransformer(BooleanRulesRequest::class);
    });

    it('terms_accepted field is optional boolean via accepted rule', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'terms_accepted');

        expect($field)->not->toBeNull()
            ->and($field['tsType'])->toBe('boolean')
            ->and($field['isRequired'])->toBeFalse();
    });

    it('is_archived field is optional nullable boolean', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'is_archived');

        expect($field)->not->toBeNull()
            ->and($field['tsType'])->toBe('boolean')
            ->and($field['isNullable'])->toBeTrue();
    });
});

describe('NumberRulesRequest fields', function () {
    beforeEach(function () {
        $this->transformer = new FormRequestTransformer(NumberRulesRequest::class);
    });

    it('score field is required number from integer and between rules', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'score');

        expect($field)->not->toBeNull()
            ->and($field['tsType'])->toBe('number')
            ->and($field['isRequired'])->toBeTrue();
    });

    it('price field is required number from decimal rule', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'price');

        expect($field)->not->toBeNull()
            ->and($field['tsType'])->toBe('number')
            ->and($field['isRequired'])->toBeTrue();
    });
});

describe('StringRulesRequest fields', function () {
    beforeEach(function () {
        $this->transformer = new FormRequestTransformer(StringRulesRequest::class);
    });

    it('email field is required string with @format email', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'email');

        expect($field)->not->toBeNull()
            ->and($field['tsType'])->toBe('string')
            ->and($field['isRequired'])->toBeTrue()
            ->and($field['jsDocMetadata'])->toContain('@format email');
    });

    it('media_type field is required union type from Rule::enum', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'media_type');

        expect($field)->not->toBeNull()
            ->and($field['isRequired'])->toBeTrue()
            ->and($field['tsType'])->toContain("'image'");
    });

    it('ip_address field is optional nullable string with @format ip', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'ip_address');

        expect($field)->not->toBeNull()
            ->and($field['isNullable'])->toBeTrue()
            ->and($field['jsDocMetadata'])->toContain('@format ip');
    });

    it('request_id field is optional nullable string with @format uuid', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'request_id');

        expect($field)->not->toBeNull()
            ->and($field['isNullable'])->toBeTrue()
            ->and($field['jsDocMetadata'])->toContain('@format uuid');
    });
});

describe('DateRulesRequest fields', function () {
    beforeEach(function () {
        $this->transformer = new FormRequestTransformer(DateRulesRequest::class);
    });

    it('event_date field is required string with @format date', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'event_date');

        expect($field)->not->toBeNull()
            ->and($field['tsType'])->toBe('string')
            ->and($field['isRequired'])->toBeTrue()
            ->and($field['jsDocMetadata'])->toContain('@format date');
    });

    it('cancelled_at field is optional nullable string with @format date', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'cancelled_at');

        expect($field)->not->toBeNull()
            ->and($field['tsType'])->toBe('string')
            ->and($field['isNullable'])->toBeTrue()
            ->and($field['jsDocMetadata'])->toContain('@format date');
    });
});

describe('FileRulesRequest fields', function () {
    beforeEach(function () {
        $this->transformer = new FormRequestTransformer(FileRulesRequest::class);
    });

    it('document field is required File type', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'document');

        expect($field)->not->toBeNull()
            ->and($field['tsType'])->toBe('File')
            ->and($field['isRequired'])->toBeTrue();
    });

    it('large_video field is optional nullable File', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'large_video');

        expect($field)->not->toBeNull()
            ->and($field['tsType'])->toBe('File')
            ->and($field['isRequired'])->toBeFalse()
            ->and($field['isNullable'])->toBeTrue();
    });
});

describe('ArrayRulesRequest fields', function () {
    beforeEach(function () {
        $this->transformer = new FormRequestTransformer(ArrayRulesRequest::class);
    });

    it('tags field is optional string array', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'tags');

        expect($field)->not->toBeNull()
            ->and($field['tsType'])->toBe('string[]')
            ->and($field['isRequired'])->toBeFalse();
    });

    it('selected_ids field is required number array', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'selected_ids');

        expect($field)->not->toBeNull()
            ->and($field['tsType'])->toBe('number[]')
            ->and($field['isRequired'])->toBeTrue();
    });

    it('order.id nested field is optional string with @format uuid', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'order.id');

        expect($field)->not->toBeNull()
            ->and($field['tsType'])->toBe('string')
            ->and($field['isRequired'])->toBeFalse()
            ->and($field['jsDocMetadata'])->toContain('@format uuid');
    });
});

describe('DatabaseRulesRequest fields', function () {
    beforeEach(function () {
        $this->transformer = new FormRequestTransformer(DatabaseRulesRequest::class);
    });

    it('state field is required string with @constraint exists', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'state');

        expect($field)->not->toBeNull()
            ->and($field['tsType'])->toBe('string')
            ->and($field['isRequired'])->toBeTrue()
            ->and($field['jsDocMetadata'])->toContain('@constraint exists');
    });

    it('email field has both @format email and @constraint unique', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'email');

        expect($field)->not->toBeNull()
            ->and($field['jsDocMetadata'])->toContain('@format email')
            ->and($field['jsDocMetadata'])->toContain('@constraint unique');
    });

    it('parent_id field is optional nullable number with @constraint exists', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'parent_id');

        expect($field)->not->toBeNull()
            ->and($field['tsType'])->toBe('number')
            ->and($field['isRequired'])->toBeFalse()
            ->and($field['isNullable'])->toBeTrue()
            ->and($field['jsDocMetadata'])->toContain('@constraint exists');
    });
});

describe('UtilityRulesRequest fields', function () {
    beforeEach(function () {
        $this->transformer = new FormRequestTransformer(UtilityRulesRequest::class);
    });

    it('name field is required string', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'name');

        expect($field)->not->toBeNull()
            ->and($field['tsType'])->toBe('string')
            ->and($field['isRequired'])->toBeTrue();
    });

    it('middle_name field is optional nullable string', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'middle_name');

        expect($field)->not->toBeNull()
            ->and($field['tsType'])->toBe('string')
            ->and($field['isRequired'])->toBeFalse()
            ->and($field['isNullable'])->toBeTrue();
    });

    it('admin_override field is marked as prohibited', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'admin_override');

        expect($field)->not->toBeNull()
            ->and($field['isProhibited'])->toBeTrue();
    });
});

describe('DynamicRequest fields', function () {
    beforeEach(function () {
        $this->transformer = new FormRequestTransformer(DynamicRequest::class);
    });

    it('is marked as dynamic', function () {
        expect($this->transformer->isDynamic)->toBeTrue();
    });

    it('has no fields when dynamic', function () {
        expect($this->transformer->fields)->toBe([]);
    });
});

describe('RuleClassRequest fields', function () {
    beforeEach(function () {
        $this->transformer = new FormRequestTransformer(RuleClassRequest::class);
    });

    it('has correct typeName regardless of dynamic status', function () {
        expect($this->transformer->typeName)->toBe('RuleClassRequest');
    });

    it('is not dynamic because the analyzer stubs Auth::user() with a GenericUser returning false', function () {
        // The FormRequestRulesAnalyzer sets a stub user before calling rules(), so
        // Auth::user()->isAdmin() returns false instead of throwing on null.
        expect($this->transformer->isDynamic)->toBeFalse();
    });

    it('zones field is required union type from Rule::in', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'zones');

        expect($field)->not->toBeNull()
            ->and($field['isRequired'])->toBeTrue()
            ->and($field['tsType'])->toContain("'first-zone'");
    });

    it('photo field is required File type', function () {
        $field = collect($this->transformer->fields)->firstWhere('fieldPath', 'photo');

        expect($field)->not->toBeNull()
            ->and($field['tsType'])->toBe('File')
            ->and($field['isRequired'])->toBeTrue();
    });
});
