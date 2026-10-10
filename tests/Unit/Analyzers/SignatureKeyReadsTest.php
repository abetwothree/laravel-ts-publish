<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ExtendedNotesResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\MergedAddressResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\MergedHeldEnumKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\MergedResourceCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\MergedValueReadsResource;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Workbench\App\Http\Resources\MergedSignatureKeysResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\Tag;

/**
 * Each property of one analyzed method as `name` => `?type`, a name's last entry winning as a publisher reads it.
 *
 * @param  class-string  $class
 * @param  class-string  $model
 * @return array<string, string>
 */
function signatureShape(string $class, string $method, string $model = Post::class): array
{
    $shape = [];

    foreach (new ResourceAstAnalyzer(new ReflectionClass($class), $model, $method)->analyze()->properties as $property) {
        $shape[$property['name']] = ($property['optional'] ? '?' : '').$property['type'];
    }

    return $shape;
}

describe('a runtime key a signature covers reaches the reconcile wherever it is written', function () {
    test('an interpolated key in the returned array and in a merge closure joins the same-pattern keys', function () {
        expect(signatureShape(MergedSignatureKeysResource::class, 'toArray', Tag::class))->toMatchArray([
            'id' => 'number',
            '[key: `${string}_note`]' => 'string | number | undefined',
            'main_label' => 'string',
            '[key: `${string}_label`]' => 'number | string | undefined',
        ]);
    });

    test('a merge closure returning the model merges its serialized keys, each optional behind the condition', function () {
        expect(signatureShape(MergedSignatureKeysResource::class, 'toArray', Tag::class))->toMatchArray([
            'name' => '?string',
            'slug' => '?string',
            'color' => '?string | null',
            'posts' => '?Post[]',
        ]);
    });

    test('a concatenated key is a signature, and an interpolated key in a nested array is one of its shape', function () {
        expect(signatureShape(MergedValueReadsResource::class, 'toArray'))->toBe([
            '[key: `tag_${string}`]' => 'boolean | undefined',
            'box' => '{ [key: `${string}_inner`]: number | undefined; x: number }',
        ]);
    });

    test('a merge closure returning the model keeps a key set before it, and its own keys optional', function () {
        expect(signatureShape(MergedValueReadsResource::class, 'closureModel'))->toMatchArray([
            'id' => 'number',
            'title' => '?string',
            'is_pinned' => '?boolean',
        ]);
    });

    test('a merge closure returning a method call merges the method\'s keys', function () {
        expect(signatureShape(MergedValueReadsResource::class, 'closureMethod'))->toBe([
            'id' => 'number',
            'extra_a' => 'number',
            'extra_b' => 'string',
        ]);
    });

    test('a model or a method call passed without a closure merges as a closure returning it would', function () {
        expect(signatureShape(MergedValueReadsResource::class, 'directValues'))->toMatchArray([
            'id' => '?number',
            'title' => '?string',
            'extra_a' => 'number',
            'extra_b' => 'string',
        ]);
    });

    test('a key set before a merge keeps its value and presence, as Laravel unions the merged keys after it', function () {
        expect(signatureShape(MergedValueReadsResource::class, 'keyBeforeMerge'))->toBe(['id' => 'string']);
    });

    test('a key set before a merge keeps none of the merged key\'s import channels', function () {
        $analysis = new ResourceAstAnalyzer(new ReflectionClass(MergedHeldEnumKeyResource::class), Post::class)->analyze();
        $transformer = new ResourceTransformer(MergedHeldEnumKeyResource::class);

        expect($analysis->directEnumFqcns)->not->toHaveKey('status')
            ->and($transformer->properties['status']['type'])->toBe('string')
            ->and(implode(' ', array_merge(...array_values($transformer->typeImports))))->not->toContain('StatusType');
    });

    test('a += keeps each signature entry beside the ones the variable holds', function () {
        expect(signatureShape(MergedValueReadsResource::class, 'plusNotes'))
            ->toBe(['[key: `${string}_note`]' => 'string | number | undefined']);
    });

    test('a second key write of one pattern keeps the first, while a named key written twice keeps its last value', function () {
        $analysis = new ResourceAstAnalyzer(new ReflectionClass(MergedValueReadsResource::class), Post::class, 'twoNoteWrites')->analyze();

        expect(signatureShape(MergedValueReadsResource::class, 'twoNoteWrites'))
            ->toBe(['[key: `${string}_note`]' => 'string | number | undefined', 'main' => 'number'])
            ->and(array_column($analysis->properties, 'name'))->toBe(['[key: `${string}_note`]', 'main']);
    });

    test('a collection merging its own resource merges no model keys', function () {
        expect(signatureShape(MergedResourceCollection::class, 'toArray'))->toBe(['meta' => 'number']);
    });

    test('a put-back signature folds its entries into one where their body values can join', function () {
        expect(signatureShape(MergedValueReadsResource::class, 'putBackNotes'))->toMatchArray([
            '[key: `${string}_note`]' => 'string | number | undefined',
            'main_note' => 'PostResource',
        ]);
    });

    it('keeps every entry a union folded when an extends clause puts the signature back', function () {
        $properties = new ResourceTransformer(ExtendedNotesResource::class)->properties;

        expect($properties['[key: `${string}_note`]'])->toMatchArray(['type' => 'string | number | undefined', 'optional' => false]);
    });

    it('applies the model\'s #[TsCasts] to a key a merged model brings', function () {
        $properties = new ResourceTransformer(MergedAddressResource::class)->properties;

        expect($properties['latitude'])->toMatchArray(['type' => 'number | null', 'optional' => true]);
    });
});

describe('a merge closure\'s unread return leaves an enclosing returned variable unread', function () {
    test('the issue\'s shape: the merged model keeps title optional where its condition can fail', function () {
        expect(signatureShape(MergedValueReadsResource::class, 'pinnedModel'))->toMatchArray([
            'id' => 'number',
            'title' => '?string',
            'content' => '?string',
        ]);
    });

    test('a model merged on every path keeps title required', function () {
        expect(signatureShape(MergedValueReadsResource::class, 'pinnedMergedModel'))->toMatchArray([
            'id' => 'number',
            'title' => 'string',
            'content' => '?string',
        ]);
    });

    test('a return the analysis cannot read skips the variable, so the literal keeps its keys required', function (string $method) {
        expect(signatureShape(MergedValueReadsResource::class, $method))->toBe(['id' => 'number', 'extra_a' => 'number']);
    })->with([
        'a closure returning a static call' => ['unreadClosure'],
        'a closure returning a variable the gate rejects' => ['unreadVariable'],
        'a static call passed as it is' => ['unreadValue'],
    ]);
});
