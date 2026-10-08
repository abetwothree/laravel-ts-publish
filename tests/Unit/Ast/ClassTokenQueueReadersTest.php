<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\HandoverLedger;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\HandoverLedgerExceptResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\HandoverLedgerOnlyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\HandoverLedgerResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\SameNameAddressResource;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;

/**
 * The type each property of a resource publishes.
 *
 * @param  class-string  $resource
 * @return array<string, string>
 */
function publishedTypes(string $resource): array
{
    return array_map(
        static fn (array $property): string => $property['type'],
        (new ResourceTransformer($resource))->properties,
    );
}

dataset('queues that do not line up', [
    'a class queued twice behind one token, then a single and a list' => [
        'who_and_crm',
        '{ who: { k: WorkbenchUser | null; n: number }; crm: CrmUser | CrmUser[] | null }',
    ],
    'a single and a list, then the other class' => [
        'watchers_then_receiver',
        '{ a: WorkbenchUser | WorkbenchUser[] | null; b: CrmUser | null }',
    ],
    'a single and a list, then a class queued twice behind one token' => [
        'crm_then_lead',
        '{ crm: CrmUser | CrmUser[] | null; lead: WorkbenchUser | string | null }',
    ],
]);

// An array's queue is its keys' queues end to end, and a union does not queue one entry per token: the totals can agree
// while a later key's token reads another key's class.
describe('an accessor whose array queues a class off its tokens', function () {
    test('the model file puts each token on its own class', function (string $accessor, string $type) {
        expect((new ModelTransformer(HandoverLedger::class))->data()->mutators[$accessor]['type'])->toBe($type);
    })->with('queues that do not line up');

    test('a resource that reads it publishes the same line', function (string $accessor, string $type) {
        expect(publishedTypes(HandoverLedgerResource::class)[$accessor])->toBe($type);
    })->with('queues that do not line up');

    test('a resource that publishes the model\'s attributes except some reads it the same way', function (string $accessor, string $type) {
        expect(publishedTypes(HandoverLedgerExceptResource::class)[$accessor])->toBe($type);
    })->with('queues that do not line up');
});

describe('a union of resources that share a name, under one key of an inline array', function () {
    test('keeps the next key on its own resource', function (string $property, string $type) {
        expect(publishedTypes(HandoverLedgerResource::class)[$property])->toBe($type);
    })->with([
        'a single and a list of one resource' => [
            'resource_pair',
            '{ x: WorkbenchUserResource | WorkbenchUserResource[] | null; y: CrmUserResource | null }',
        ],
        'two arrays naming one resource' => [
            'resource_arms',
            '{ w: { r: WorkbenchUserResource | null } | { r: WorkbenchUserResource | null; q: number }; y: CrmUserResource | null }',
        ],
    ]);
});

// A member that spells a name for a model and for a resource queues one class on each channel. The queues are merged,
// models first, so filling both to the token count would read the model on every token of the name.
describe('a member that spells one name for a model and for a resource', function () {
    test('keeps each token on its own class', function (string $property, string $type) {
        expect(publishedTypes(SameNameAddressResource::class)[$property])->toBe($type);
    })->with([
        'a pair, one array under another' => [
            'data_pair',
            '{ data: { raw: WorkbenchAddress2 | null; address: WorkbenchAddress | null } }',
        ],
        'a list of each' => [
            'data_lists',
            '{ data: { raw: WorkbenchAddress2[]; list: WorkbenchAddress[] } }',
        ],
        'a pair, then the resource again' => [
            'data_pair_then_resource',
            '{ data: { raw: WorkbenchAddress2 | null; address: WorkbenchAddress | null }; again: WorkbenchAddress | null }',
        ],
        'a union of the model\'s list and the resource' => [
            'models_or_resource',
            '{ x: WorkbenchAddress2[] | WorkbenchAddress | null }',
        ],
        'a model or its list in one member, the resource in the next' => [
            'model_or_list_then_resource',
            '{ m: WorkbenchAddress2 | WorkbenchAddress2[] | null; r: WorkbenchAddress | null }',
        ],
    ]);
});

describe('an accessor named after a column', function () {
    test('publishes the column as the accessor types it, each token on its own class', function () {
        expect((new ModelTransformer(HandoverLedger::class))->data()->columns['updated_at']['type'])
            ->toBe('{ first: WorkbenchUser | null; either: WorkbenchUser | CrmUser | null }');
    });
});

describe('every way into a resource hands an accessor\'s queue on', function () {
    test('publishes the union of both classes, one on each token', function (string $resource, string $property, string $type) {
        expect(publishedTypes($resource)[$property])->toBe($type);
    })->with([
        '$this->only()' => [
            HandoverLedgerOnlyResource::class,
            'parties',
            '{ first: WorkbenchUser | null; either: WorkbenchUser | CrmUser | null }',
        ],
        '$this->except()' => [
            HandoverLedgerExceptResource::class,
            'parties',
            '{ first: WorkbenchUser | null; either: WorkbenchUser | CrmUser | null }',
        ],
        'a variable bound to the model' => [
            HandoverLedgerResource::class,
            'bound_parties',
            '{ first: WorkbenchUser | null; either: WorkbenchUser | CrmUser | null }',
        ],
        'a relation\'s only()' => [
            HandoverLedgerResource::class,
            'twin_parties',
            '{ parties: { first: WorkbenchUser | null; either: WorkbenchUser | CrmUser | null } }',
        ],
    ]);
});
