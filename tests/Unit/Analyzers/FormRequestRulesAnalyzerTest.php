<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\FormRequest\FormRequestRulesAnalyzer;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Workbench\App\Http\Requests\ArrayRulesRequest;
use Workbench\App\Http\Requests\BooleanRulesRequest;
use Workbench\App\Http\Requests\DateRulesRequest;
use Workbench\App\Http\Requests\DynamicRequest;
use Workbench\App\Http\Requests\FileRulesRequest;
use Workbench\App\Http\Requests\RuleClassRequest;
use Workbench\App\Http\Requests\StorePostRequest;
use Workbench\App\Http\Requests\StringRulesRequest;
use Workbench\App\Http\Requests\UpdatePostRequest;
use Workbench\App\Http\Requests\UtilityRulesRequest;

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

        it('maps date and date_equals fields to @format date (not date-time)', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(DateRulesRequest::class);

            $eventDate = collect($nodes)->firstWhere('fieldPath', 'event_date');
            expect($eventDate)->not->toBeNull();
            expect($eventDate->jsDocMetadata)->toContain('@format date');
            expect($eventDate->jsDocMetadata)->not->toContain('@format date-time');

            $releaseDate = collect($nodes)->firstWhere('fieldPath', 'release_date');
            expect($releaseDate)->not->toBeNull();
            expect($releaseDate->jsDocMetadata)->toContain('@format date');
            expect($releaseDate->jsDocMetadata)->not->toContain('@format date-time');
        });

        it('maps extensions rule to File type', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(FileRulesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'photo');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('File');
        });

        it('maps File::types() object rule to File type', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(FileRulesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'small_attachment');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('File');
        });

        it('maps File::image() object rule (ImageFile) to File type', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(FileRulesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'banner');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('File');
        });

        it('infers string[] element type for array field with wildcard string rule', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(ArrayRulesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'tags');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('string[]');
        });

        it('infers number[] element type for array field with wildcard integer rule', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(ArrayRulesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'selected_ids');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('number[]');
        });

        it('infers string[] for nullable array field with wildcard string rule', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(ArrayRulesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'limited_choices');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('string[]');
            expect($node->isNullable)->toBeTrue();
        });

        it('infers string[] for nested wildcard array field', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(ArrayRulesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'products.*.categories');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('string[]');
        });

        it('forces dot-notation and wildcard paths to always be optional regardless of required rule', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(ArrayRulesRequest::class);

            // Wildcard element path with required validator — must be optional
            $tagsWildcard = collect($nodes)->firstWhere('fieldPath', 'tags.*');
            expect($tagsWildcard)->not->toBeNull();
            expect($tagsWildcard->isRequired)->toBeFalse();

            // Deep dot-notation path with required validator — must be optional
            $orderId = collect($nodes)->firstWhere('fieldPath', 'order.id');
            expect($orderId)->not->toBeNull();
            expect($orderId->isRequired)->toBeFalse();

            // Deep nested wildcard with required validator — must be optional
            $productName = collect($nodes)->firstWhere('fieldPath', 'products.*.name');
            expect($productName)->not->toBeNull();
            expect($productName->isRequired)->toBeFalse();
        });

        it('maps Rule::anyOf with all-string inner rules to string type', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(UtilityRulesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'contact');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('string');
        });

        it('maps Rule::string() fluent object to string type', function () {
            $nodes = (new FormRequestRulesAnalyzer)->analyze(RuleClassRequest::class);
            $node = collect($nodes)->firstWhere('fieldPath', 'title');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('string');
        });

        it('maps Rule::email() fluent object to string type', function () {
            $nodes = (new FormRequestRulesAnalyzer)->analyze(RuleClassRequest::class);
            $node = collect($nodes)->firstWhere('fieldPath', 'email');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('string');
        });

        it('maps Rule::date() fluent object to string type', function () {
            $nodes = (new FormRequestRulesAnalyzer)->analyze(RuleClassRequest::class);
            $node = collect($nodes)->firstWhere('fieldPath', 'start_date');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('string');
        });

        it('maps nullable Rule::date() fluent object to string type with isNullable', function () {
            $nodes = (new FormRequestRulesAnalyzer)->analyze(RuleClassRequest::class);
            $node = collect($nodes)->firstWhere('fieldPath', 'end_date');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('string');
            expect($node->isNullable)->toBeTrue();
        });

        it('maps Rule::dimensions() fluent object to File type', function () {
            $nodes = (new FormRequestRulesAnalyzer)->analyze(RuleClassRequest::class);
            $node = collect($nodes)->firstWhere('fieldPath', 'avatar');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('File');
        });

        it('maps Rule::notIn() fluent object to string type', function () {
            $nodes = (new FormRequestRulesAnalyzer)->analyze(RuleClassRequest::class);
            $node = collect($nodes)->firstWhere('fieldPath', 'toppings');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('string');
        });

        it('maps Rule::numeric() fluent object to number type', function () {
            $nodes = (new FormRequestRulesAnalyzer)->analyze(RuleClassRequest::class);
            $node = collect($nodes)->firstWhere('fieldPath', 'quantity');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('number');
        });

        it('marks Rule::requiredIf() field as required', function () {
            $nodes = (new FormRequestRulesAnalyzer)->analyze(RuleClassRequest::class);
            $node = collect($nodes)->firstWhere('fieldPath', 'role_id_required_if');
            expect($node)->not->toBeNull();
            expect($node->isRequired)->toBeTrue();
        });

        it('adds prohibited-if conditional metadata for Rule::prohibitedIf()', function () {
            $nodes = (new FormRequestRulesAnalyzer)->analyze(RuleClassRequest::class);
            $node = collect($nodes)->firstWhere('fieldPath', 'role_id_prohibited');
            expect($node)->not->toBeNull();
            expect($node->jsDocMetadata)->toContain('@metadata prohibited-if conditional');
        });

        it('adds exclude-if conditional metadata for Rule::excludeIf()', function () {
            $nodes = (new FormRequestRulesAnalyzer)->analyze(RuleClassRequest::class);
            $node = collect($nodes)->firstWhere('fieldPath', 'role_id');
            expect($node)->not->toBeNull();
            expect($node->jsDocMetadata)->toContain('@metadata exclude-if conditional');
        });

        it('adds @constraint exists metadata for Rule::exists() object', function () {
            $nodes = (new FormRequestRulesAnalyzer)->analyze(RuleClassRequest::class);
            $node = collect($nodes)->firstWhere('fieldPath', 'state');
            expect($node)->not->toBeNull();
            expect($node->jsDocMetadata)->toContain('@constraint exists');
        });

        it('adds @constraint unique metadata for Rule::unique() object', function () {
            $nodes = (new FormRequestRulesAnalyzer)->analyze(RuleClassRequest::class);
            $node = collect($nodes)->firstWhere('fieldPath', 'email_unique');
            expect($node)->not->toBeNull();
            expect($node->jsDocMetadata)->toContain('@constraint unique');
        });

        it('filters enum values by Rule::enum()->only()', function () {
            $nodes = (new FormRequestRulesAnalyzer)->analyze(RuleClassRequest::class);
            $node = collect($nodes)->firstWhere('fieldPath', 'accent_color');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe("'red' | 'blue'");
        });

        it('filters enum values by Rule::enum()->except()', function () {
            $nodes = (new FormRequestRulesAnalyzer)->analyze(RuleClassRequest::class);
            $node = collect($nodes)->firstWhere('fieldPath', 'forbidden_color');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe("'green' | 'blue' | 'amber' | 'gray' | 'purple'");
        });

        it('resets isDynamic to false on each analyze() call', function () {
            $analyzer = new FormRequestRulesAnalyzer;

            // First call: dynamic request → isDynamic becomes true
            $analyzer->analyze(DynamicRequest::class);
            expect($analyzer->isDynamic)->toBeTrue();

            // Second call: static request → isDynamic must reset to false
            $analyzer->analyze(StorePostRequest::class);
            expect($analyzer->isDynamic)->toBeFalse();
        });
    });

    describe('auth state restoration', function () {
        it('restores guest auth state after analyzing a static FormRequest', function () {
            Auth::forgetUser();

            (new FormRequestRulesAnalyzer)->analyze(StorePostRequest::class);

            expect(Auth::check())->toBeFalse();
        });

        it('preserves an existing authenticated user after analyzing a FormRequest', function () {
            $user = new GenericUser(['id' => 99]);
            Auth::setUser($user);

            (new FormRequestRulesAnalyzer)->analyze(StorePostRequest::class);

            expect(Auth::id())->toBe(99);

            Auth::forgetUser(); // cleanup
        });
    });
});
