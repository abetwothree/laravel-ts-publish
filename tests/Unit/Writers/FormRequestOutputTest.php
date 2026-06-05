<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Transformers\FormRequestTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\FormRequestWriter;
use Illuminate\Filesystem\Filesystem;
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

describe('StorePostRequest output', function () {
    beforeEach(function () {
        config()->set('ts-publish.output_to_files', false);
        $this->content = (new FormRequestWriter(new Filesystem))->write(
            new FormRequestTransformer(StorePostRequest::class)
        );
    });

    it('renders a static interface not a dynamic type', function () {
        expect($this->content)
            ->toContain('export interface StorePostRequest')
            ->not->toContain('@dynamic')
            ->not->toContain('export type StorePostRequest');
    });

    it('includes required fields without optional marker', function () {
        expect($this->content)
            ->toContain('title: string;')
            ->toContain('body: string;');
    });

    it('marks optional fields with ?', function () {
        expect($this->content)->toContain('published?: boolean;');
    });

    it('marks nullable optional fields with ? and | null', function () {
        expect($this->content)->toContain('rating?: number | null;');
    });

    it('includes @format email annotation before email field', function () {
        expect($this->content)
            ->toContain('@format email')
            ->toContain('email: string;');
    });

    it('includes typed optional array field', function () {
        expect($this->content)->toContain('tags?: string[];');
    });
});

describe('UpdatePostRequest output', function () {
    beforeEach(function () {
        config()->set('ts-publish.output_to_files', false);
        $this->content = (new FormRequestWriter(new Filesystem))->write(
            new FormRequestTransformer(UpdatePostRequest::class)
        );
    });

    it('marks sometimes field as optional', function () {
        expect($this->content)->toContain('title?: string;');
    });

    it('renders Rule::in as union type string literals', function () {
        expect($this->content)->toContain("status: 'draft' | 'published' | 'archived';");
    });

    it('includes @constraint exists annotation for category_id', function () {
        expect($this->content)
            ->toContain('@constraint exists')
            ->toContain('category_id: number;');
    });

    it('marks nullable integer as optional with null', function () {
        expect($this->content)->toContain('priority?: number | null;');
    });
});

describe('BooleanRulesRequest output', function () {
    beforeEach(function () {
        config()->set('ts-publish.output_to_files', false);
        $this->content = (new FormRequestWriter(new Filesystem))->write(
            new FormRequestTransformer(BooleanRulesRequest::class)
        );
    });

    it('renders accepted rule as optional boolean', function () {
        expect($this->content)->toContain('terms_accepted?: boolean;');
    });

    it('renders nullable boolean as optional with null', function () {
        expect($this->content)->toContain('is_archived?: boolean | null;');
    });
});

describe('NumberRulesRequest output', function () {
    beforeEach(function () {
        config()->set('ts-publish.output_to_files', false);
        $this->content = (new FormRequestWriter(new Filesystem))->write(
            new FormRequestTransformer(NumberRulesRequest::class)
        );
    });

    it('renders integer with between as required number', function () {
        expect($this->content)->toContain('score: number;');
    });

    it('renders decimal rule as required number', function () {
        expect($this->content)->toContain('price: number;');
    });
});

describe('StringRulesRequest output', function () {
    beforeEach(function () {
        config()->set('ts-publish.output_to_files', false);
        $this->content = (new FormRequestWriter(new Filesystem))->write(
            new FormRequestTransformer(StringRulesRequest::class)
        );
    });

    it('renders Rule::enum as inlined union of enum backing values', function () {
        expect($this->content)->toContain("media_type: 'image' | 'video' | 'audio' | 'document' | 'archive';");
    });

    it('includes @format email annotation', function () {
        expect($this->content)->toContain('@format email');
    });

    it('includes @format uuid annotation for request_id', function () {
        expect($this->content)->toContain('@format uuid');
    });

    it('includes @format ip annotation for ip_address', function () {
        expect($this->content)->toContain('@format ip');
    });
});

describe('DateRulesRequest output', function () {
    beforeEach(function () {
        config()->set('ts-publish.output_to_files', false);
        $this->content = (new FormRequestWriter(new Filesystem))->write(
            new FormRequestTransformer(DateRulesRequest::class)
        );
    });

    it('renders required date field as required string', function () {
        expect($this->content)->toContain('event_date: string;');
    });

    it('renders nullable date field as optional string with null', function () {
        expect($this->content)->toContain('cancelled_at?: string | null;');
    });

    it('includes @format date annotations for date fields', function () {
        // Both event_date and cancelled_at carry @format date
        expect(substr_count($this->content, '@format date'))->toBeGreaterThanOrEqual(2);
    });
});

describe('FileRulesRequest output', function () {
    beforeEach(function () {
        config()->set('ts-publish.output_to_files', false);
        $this->content = (new FormRequestWriter(new Filesystem))->write(
            new FormRequestTransformer(FileRulesRequest::class)
        );
    });

    it('renders file rule as required File type', function () {
        expect($this->content)->toContain('document: File;');
    });

    it('renders nullable file as optional File with null', function () {
        expect($this->content)->toContain('large_video?: File | null;');
    });
});

describe('ArrayRulesRequest output', function () {
    beforeEach(function () {
        config()->set('ts-publish.output_to_files', false);
        $this->content = (new FormRequestWriter(new Filesystem))->write(
            new FormRequestTransformer(ArrayRulesRequest::class)
        );
    });

    it('renders optional array field as typed optional array', function () {
        expect($this->content)->toContain('tags?: string[];');
    });

    it('renders required array field as typed required array', function () {
        expect($this->content)->toContain('selected_ids: number[];');
    });

    it('quotes dot-notation field names and marks them optional', function () {
        expect($this->content)->toContain('"order.id"?: string;');
    });
});

describe('DatabaseRulesRequest output', function () {
    beforeEach(function () {
        config()->set('ts-publish.output_to_files', false);
        $this->content = (new FormRequestWriter(new Filesystem))->write(
            new FormRequestTransformer(DatabaseRulesRequest::class)
        );
    });

    it('includes @constraint exists annotation', function () {
        expect($this->content)->toContain('@constraint exists');
    });

    it('includes @constraint unique annotation', function () {
        expect($this->content)->toContain('@constraint unique');
    });
});

describe('UtilityRulesRequest output', function () {
    beforeEach(function () {
        config()->set('ts-publish.output_to_files', false);
        $this->content = (new FormRequestWriter(new Filesystem))->write(
            new FormRequestTransformer(UtilityRulesRequest::class)
        );
    });

    it('renders required name field', function () {
        expect($this->content)->toContain('name: string;');
    });

    it('renders optional nullable middle_name', function () {
        expect($this->content)->toContain('middle_name?: string | null;');
    });

    it('excludes prohibited admin_override field from output', function () {
        expect($this->content)->not->toContain('admin_override');
    });
});

describe('DynamicRequest output', function () {
    beforeEach(function () {
        config()->set('ts-publish.output_to_files', false);
        $this->content = (new FormRequestWriter(new Filesystem))->write(
            new FormRequestTransformer(DynamicRequest::class)
        );
    });

    it('renders as Record type alias not an interface', function () {
        expect($this->content)
            ->toContain('export type DynamicRequest = Record<string, unknown>;')
            ->not->toContain('export interface DynamicRequest');
    });

    it('includes @dynamic marker in JSDoc', function () {
        expect($this->content)->toContain('@dynamic');
    });
});

describe('RuleClassRequest output', function () {
    beforeEach(function () {
        config()->set('ts-publish.output_to_files', false);
        $this->content = (new FormRequestWriter(new Filesystem))->write(
            new FormRequestTransformer(RuleClassRequest::class)
        );
    });

    it('renders a static interface because the analyzer stubs Auth::user()', function () {
        // The analyzer stubs Auth with a GenericUser returning false for any method,
        // so Auth::user()->isAdmin() doesn't throw and isDynamic stays false.
        expect($this->content)->toContain('export interface RuleClassRequest');
    });

    it('includes @see FQCN reference in JSDoc', function () {
        expect($this->content)->toContain('@see');
    });
});
