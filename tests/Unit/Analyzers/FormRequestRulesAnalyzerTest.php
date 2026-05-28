<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\FormRequest\FormRequestRulesAnalyzer;
use Workbench\App\Http\Requests\BooleanRulesRequest;
use Workbench\App\Http\Requests\DateRulesRequest;
use Workbench\App\Http\Requests\DynamicRequest;
use Workbench\App\Http\Requests\StorePostRequest;
use Workbench\App\Http\Requests\StringRulesRequest;
use Workbench\App\Http\Requests\UpdatePostRequest;

describe('FormRequestRulesAnalyzer', function () {
    describe('analyze', function () {
        it('returns rule nodes for a static FormRequest', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(StorePostRequest::class);

            expect($analyzer->isDynamic)->toBeFalse();
            expect($nodes)->not->toBeEmpty();

            $fieldPaths = array_map(fn ($n) => $n->fieldPath, $nodes);
            expect($fieldPaths)->toContain('title');
            expect($fieldPaths)->toContain('body');
            expect($fieldPaths)->toContain('published');
        });

        it('marks isDynamic true and returns empty list for a dynamic FormRequest', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(DynamicRequest::class);

            expect($analyzer->isDynamic)->toBeTrue();
            expect($nodes)->toBeEmpty();
        });

        it('maps string field to string type', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(StorePostRequest::class);

            $title = collect($nodes)->firstWhere('fieldPath', 'title');
            expect($title)->not->toBeNull();
            expect($title->tsType)->toBe('string');
            expect($title->isRequired)->toBeTrue();
        });

        it('maps boolean field to boolean type', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(StorePostRequest::class);

            $published = collect($nodes)->firstWhere('fieldPath', 'published');
            expect($published)->not->toBeNull();
            expect($published->tsType)->toBe('boolean');
        });

        it('maps nullable numeric field correctly', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(StorePostRequest::class);

            $rating = collect($nodes)->firstWhere('fieldPath', 'rating');
            expect($rating)->not->toBeNull();
            expect($rating->tsType)->toBe('number');
            expect($rating->isNullable)->toBeTrue();
            expect($rating->isRequired)->toBeFalse();
        });

        it('adds @format email metadata for email rule', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(StorePostRequest::class);

            $email = collect($nodes)->firstWhere('fieldPath', 'email');
            expect($email)->not->toBeNull();
            expect($email->jsDocMetadata)->toContain('@format email');
        });

        it('resolves Rule::in to a union type', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(UpdatePostRequest::class);

            $status = collect($nodes)->firstWhere('fieldPath', 'status');
            expect($status)->not->toBeNull();
            expect($status->tsType)->toContain('\'draft\'');
            expect($status->tsType)->toContain('\'published\'');
            expect($status->tsType)->toContain('\'archived\'');
        });

        it('marks sometimes field as not required', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(UpdatePostRequest::class);

            $title = collect($nodes)->firstWhere('fieldPath', 'title');
            expect($title)->not->toBeNull();
            expect($title->isRequired)->toBeFalse();
        });

        it('adds @constraint exists metadata for exists rule', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(UpdatePostRequest::class);

            $categoryId = collect($nodes)->firstWhere('fieldPath', 'category_id');
            expect($categoryId)->not->toBeNull();
            expect(implode(' ', $categoryId->jsDocMetadata))->toContain('@constraint exists');
        });

        it('maps accepted_if field to boolean type', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(BooleanRulesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'newsletter_accepted');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('boolean');
        });

        it('maps declined_if field to boolean type', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(BooleanRulesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'privacy_declined');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('boolean');
        });

        it('maps ascii field to string type', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(StringRulesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'ascii_id');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('string');
        });

        it('maps current_password field to string type', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(StringRulesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'old_password');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('string');
        });

        it('maps regex rule field to string type', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(StringRulesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'postal_code');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('string');
        });

        it('maps date_format field to string type', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(DateRulesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'formatted_date');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('string');
        });
    });
});
