<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\FormRequest\FormRequestRulesAnalyzer;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Workbench\App\Http\Requests\ArrayKeysObjectFormRequest;
use Workbench\App\Http\Requests\ArrayRulesRequest;
use Workbench\App\Http\Requests\BooleanRulesRequest;
use Workbench\App\Http\Requests\DateRulesRequest;
use Workbench\App\Http\Requests\DynamicRequest;
use Workbench\App\Http\Requests\FileRulesRequest;
use Workbench\App\Http\Requests\NestedEdgeCasesRequest;
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

        it('folds a nullable wildcard element into the array element type', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(ArrayRulesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'limited_choices');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('(string | null)[]');
            expect($node->isNullable)->toBeTrue();
        });

        it('leaves a non-nullable wildcard element unparenthesized', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(ArrayRulesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'required_answers');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('string[]');
        });

        it('never emits a dotted or wildcarded field path at the top level', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(ArrayRulesRequest::class);

            foreach ($nodes as $node) {
                expect($node->fieldPath)->not->toContain('.');
            }
        });

        it('maps Rule::anyOf with all-string inner rules to string type', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(UtilityRulesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'contact');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('string');
        });

        it('string-form in outranks an earlier string rule', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(UtilityRulesRequest::class);

            $role = collect($nodes)->firstWhere('fieldPath', 'role');
            expect($role->tsType)->toBe("'user' | 'admin' | 'moderator'");
        });

        it('string-form in under sometimes stays optional with the literal union', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(UtilityRulesRequest::class);

            $pref = collect($nodes)->firstWhere('fieldPath', 'optional_preference');
            expect($pref->tsType)->toBe("'light' | 'dark' | 'system'");
            expect($pref->isRequired)->toBeFalse();
        });

        // ValidationRuleParser::parse() always parses string-form params as strings, so numeric-looking
        // params need the sibling `integer` rule as a signal to emit unquoted, matching what
        // `Rule::in([1, 2, 3])` (the object form) already emits for the same values.
        it('string-form in with a sibling integer rule emits unquoted numeric literals', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(UtilityRulesRequest::class);

            $priority = collect($nodes)->firstWhere('fieldPath', 'priority_level');
            expect($priority->tsType)->toBe('1 | 2 | 3');
        });

        // Contrast: a declared `string` field's `in:1,2,3` params stay quoted string literals — the
        // numeric trigger must not retype a field the rules explicitly say is a string.
        it('string-form in on a declared string field keeps quoted string literals', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(UtilityRulesRequest::class);

            $legacy = collect($nodes)->firstWhere('fieldPath', 'legacy_code');
            expect($legacy->tsType)->toBe("'1' | '2' | '3'");
        });

        // Guard: '007' -> 7 and '2.50' -> 2.5 both lose information through +0 coercion. validateIn()
        // compares (string) $value against the literal param, so the unquoted, renormalized number would
        // describe a value Laravel itself rejects for this field — these must stay quoted even though the
        // field carries the numeric-trigger `numeric` rule.
        it('keeps a padded or reformatted numeric in: param quoted instead of renormalizing it', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(UtilityRulesRequest::class);

            $padded = collect($nodes)->firstWhere('fieldPath', 'padded_numeric_code');
            expect($padded->tsType)->toBe("'007' | '2.50'");
        });

        it('a sibling digits rule makes a string-form in: emit unquoted numeric literals', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(UtilityRulesRequest::class);

            $grade = collect($nodes)->firstWhere('fieldPath', 'digit_grade');
            expect($grade->tsType)->toBe('1 | 2 | 3');
        });

        it('a sibling decimal rule makes a string-form in: emit unquoted numeric literals', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(UtilityRulesRequest::class);

            $tier = collect($nodes)->firstWhere('fieldPath', 'decimal_tier');
            expect($tier->tsType)->toBe('1.5 | 2.5');
        });

        // Guard, not a discriminator: this also passes with fieldIsNumeric() reverted to integer/int/numeric,
        // where `decimal` stops counting and the params stay quoted for the other reason. The two tests above
        // are what pin the widened list. This pins that padding survives whichever list is in force.
        it('a padded decimal in: param stays quoted whichever rules count as numeric', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(UtilityRulesRequest::class);

            $padded = collect($nodes)->firstWhere('fieldPath', 'padded_decimal_tier');
            expect($padded->tsType)->toBe("'1.50' | '2.50'");
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

        it('maps Rule::arrayKeys() fluent object to unknown[] type, unlike the string form', function () {
            $nodes = (new FormRequestRulesAnalyzer)->analyze(ArrayKeysObjectFormRequest::class);
            $node = collect($nodes)->firstWhere('fieldPath', 'attributes_map');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('unknown[]');
            expect($node->isRequired)->toBeTrue();
        })->skip(
            ! class_exists('Illuminate\Validation\Rules\ArrayKeys'),
            'Rule::arrayKeys() requires Laravel 13.24+',
        );

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

    describe('analyzeField', function () {
        it('composes a dotted path from the rule trie', function () {
            $node = (new FormRequestRulesAnalyzer)->analyzeField(NestedEdgeCasesRequest::class, 'options.default');

            expect($node)->not->toBeNull()
                ->and($node->fieldPath)->toBe('options.default')
                ->and($node->tsType)->toBe('string')
                ->and($node->isProhibited)->toBeFalse();
        });

        it('returns null for a path the rules do not declare', function () {
            expect((new FormRequestRulesAnalyzer)->analyzeField(NestedEdgeCasesRequest::class, 'options.missing'))->toBeNull();
        });

        it('tracks isDynamic across analyzeField() calls, like analyze() does', function () {
            $analyzer = new FormRequestRulesAnalyzer;

            expect($analyzer->analyzeField(DynamicRequest::class, 'name'))->toBeNull();
            expect($analyzer->isDynamic)->toBeTrue();

            expect($analyzer->analyzeField(NestedEdgeCasesRequest::class, 'options.default'))->not->toBeNull();
            expect($analyzer->isDynamic)->toBeFalse();
        });
    });

    describe('nested array rule composition', function () {
        it('composes parent.*.child rules into a typed element object', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(ArrayRulesRequest::class);

            $products = collect($nodes)->firstWhere('fieldPath', 'products');
            expect($products)->not->toBeNull();
            expect($products->tsType)
                ->toContain('name: string')
                ->toContain('price: number')
                ->toContain('quantity: number')
                ->toContain('categories: string[]')
                ->toContain('is_available: boolean')
                ->toEndWith('[]');
            expect($products->isRequired)->toBeTrue();
        });

        it('marks a nested child without a required rule as an optional, nullable key', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(ArrayRulesRequest::class);

            $products = collect($nodes)->firstWhere('fieldPath', 'products');
            expect($products->tsType)->toContain('notes?: string | null');
        });

        it('recursively nests dotted parents and arrayifies wildcard segments', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(ArrayRulesRequest::class);

            $order = collect($nodes)->firstWhere('fieldPath', 'order');
            expect($order)->not->toBeNull();
            expect($order->tsType)->toBe('{ id: string; items: { product_id: number; quantity: number }[] }');
            expect($order->isRequired)->toBeTrue();
        });

        it('drops flat composed keys once folded into their parent', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(ArrayRulesRequest::class);

            $fieldPaths = array_map(fn ($n) => $n->fieldPath, $nodes);

            expect($fieldPaths)
                ->not->toContain('products.*.name')
                ->not->toContain('products.*.categories')
                ->not->toContain('products.*.categories.*')
                ->not->toContain('order.id')
                ->not->toContain('order.items')
                ->not->toContain('order.items.*.product_id')
                ->not->toContain('tags.*')
                ->not->toContain('roles.*');
        });

        it('keeps one-level wildcard scalar composition unchanged: roles.* -> roles: string[]', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(ArrayRulesRequest::class);

            $roles = collect($nodes)->firstWhere('fieldPath', 'roles');
            expect($roles)->not->toBeNull();
            expect($roles->tsType)->toBe('string[]');
            expect($roles->isRequired)->toBeTrue();

            $tags = collect($nodes)->firstWhere('fieldPath', 'tags');
            expect($tags)->not->toBeNull();
            expect($tags->tsType)->toBe('string[]');
            expect($tags->isRequired)->toBeFalse();
        });

        it('composes required_array_keys into a keyed unknown object when there are no wildcard children', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(UtilityRulesRequest::class);

            $permissions = collect($nodes)->firstWhere('fieldPath', 'permissions');
            expect($permissions)->not->toBeNull();
            expect($permissions->tsType)->toBe('{ read: unknown; write: unknown }');
            expect($permissions->isRequired)->toBeTrue();
        });

        it('composes in_array_keys into a keyed object with optional keys', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(ArrayRulesRequest::class);

            $config = collect($nodes)->firstWhere('fieldPath', 'config');
            expect($config)->not->toBeNull();
            expect($config->tsType)->toBe('{ timezone?: unknown }');
            expect($config->isRequired)->toBeTrue();
        });

        it('composes array:k1,k2 into a keyed object with optional keys', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(ArrayRulesRequest::class);

            $preferences = collect($nodes)->firstWhere('fieldPath', 'preferences');
            expect($preferences)->not->toBeNull();
            expect($preferences->tsType)->toBe('{ theme?: unknown; locale?: unknown }');
            expect($preferences->isRequired)->toBeFalse();
        });

        it('array_keys synthesizes optional keys like array:a,b', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(ArrayRulesRequest::class);

            $map = collect($nodes)->firstWhere('fieldPath', 'attributes_map');
            expect($map->tsType)->toBe('{ color?: unknown; size?: unknown }');
            expect($map->isRequired)->toBeTrue();
        });

        it('degrades a parameterless array_keys to unknown[] instead of bare unknown', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(ArrayRulesRequest::class);

            $malformed = collect($nodes)->firstWhere('fieldPath', 'malformed_array_keys');
            expect($malformed)->not->toBeNull();
            expect($malformed->tsType)->toBe('unknown[]');
            expect($malformed->isRequired)->toBeTrue();
        });

        it('merges required_array_keys synthesis with a real declared child, real child winning the collision', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(ArrayRulesRequest::class);

            $shipping = collect($nodes)->firstWhere('fieldPath', 'shipping');
            expect($shipping)->not->toBeNull();
            expect($shipping->tsType)->toBe("{ method?: 'standard' | 'express' | null; address: unknown }");
            expect($shipping->isRequired)->toBeTrue();
        });

        it('hoists a nested field JSDoc annotation onto the composed parent', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(ArrayRulesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'order');
            expect($node)->not->toBeNull();
            expect($node->jsDocMetadata)->toContain('@format uuid order.id');
        });

        it('keeps wildcard segments in the hoisted metadata path', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(ArrayRulesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'products');
            expect($node)->not->toBeNull();
            expect($node->jsDocMetadata)->toContain('@format email products.*.contact_email');
        });

        it('drops a prohibited nested field and its descendants from the hoisted metadata', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(ArrayRulesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'order');
            expect($node)->not->toBeNull();
            expect($node->jsDocMetadata)->not->toContain('@format uuid order.secret.token');
            expect($node->tsType)->not->toContain('secret');
        });
    });

    describe('composition edge cases', function () {
        it('intersects a wildcard with its named siblings and folds its nullable element into the Record value', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(NestedEdgeCasesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'options');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('{ default?: string } & Record<string, string | null>');
            expect($node->tsType)->not->toContain('"*"');
        });

        it('types an all-prohibited object as an empty record, not "{  }"', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(NestedEdgeCasesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'meta');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('Record<string, never>');
        });

        it('types an array with a prohibited element as never[]', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(NestedEdgeCasesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'empties');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('never[]');
        });

        it('treats an escaped dot as a literal attribute name, not a path separator', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(NestedEdgeCasesRequest::class);

            $paths = array_map(fn ($n) => $n->fieldPath, $nodes);
            expect($paths)->toContain('v1.0');

            $node = collect($nodes)->firstWhere('fieldPath', 'v1.0');
            expect($node->tsType)->toBe('string');
            expect($node->isRequired)->toBeTrue();
        });

        it('composes explicit numeric indices as an array element, not a "0" key', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(NestedEdgeCasesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'items');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('{ name: string }[]');
            expect($node->tsType)->not->toContain('"0"');
        });

        it('parenthesizes a union of differently-shaped numeric indices before array-wrapping', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(NestedEdgeCasesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'variants');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('({ name: string } | { email: string })[]');
            expect($node->tsType)->not->toBe('{ name: string } | { email: string }[]');
        });

        it('does not let a bracket character inside an "in:" literal unbalance the union scan', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(NestedEdgeCasesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'markers');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe("('>a' | 'b')[]");
            expect($node->tsType)->not->toBe("'>a' | 'b'[]");
        });

        it('parenthesizes a top-level intersection before array-wrapping', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(NestedEdgeCasesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'buckets');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('({ name?: string } & Record<string, string>)[]');
            expect($node->tsType)->not->toBe('{ name?: string } & Record<string, string>[]');
        });

        it('escapes an apostrophe inside an "in:" value instead of breaking out of the TS literal', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(NestedEdgeCasesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'quoted');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe("'it\\'s' | 'b'");
        });

        it('types a mixed node with a prohibited wildcard element as never, not the residual type', function () {
            $analyzer = new FormRequestRulesAnalyzer;
            $nodes = $analyzer->analyze(NestedEdgeCasesRequest::class);

            $node = collect($nodes)->firstWhere('fieldPath', 'settings');
            expect($node)->not->toBeNull();
            expect($node->tsType)->toBe('{ color?: string } & Record<string, never>');
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
