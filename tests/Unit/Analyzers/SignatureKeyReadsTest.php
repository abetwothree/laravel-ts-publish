<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedModelRegistry;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\EnumThenStringSignatureResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ExtendedNotesResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\IndexSignatureConflictResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\MergedAddressResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\MergedHeldEnumKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\MergedResourceCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\MergedValueReadsResource;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Workbench\App\Http\Resources\MergedSignatureKeysResource;
use Workbench\App\Http\Resources\PostResource;
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

    test('a signature whose first entry reads an enum and a later one a string imports no enum it does not spell', function () {
        $transformer = new ResourceTransformer(EnumThenStringSignatureResource::class);
        $type = $transformer->properties['[key: `${string}_state`]']['type'];
        $imports = implode(' ', array_merge(...array_values($transformer->typeImports)));

        foreach (['StatusType', 'Status'] as $name) {
            expect(str_contains($imports, $name))->toBe(str_contains($type, $name));
        }
    });

    test('a whole-array write keeps every signature entry it holds apart, for a later reconcile to join', function () {
        expect(signatureShape(MergedValueReadsResource::class, 'wholeWriteNotes'))
            ->toBe(['[key: `${string}_note`]' => 'string | number | undefined', 'main_note' => 'number']);
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
        expect(signatureShape(MergedValueReadsResource::class, $method))->toMatchArray(['id' => 'number', 'extra_a' => 'number']);
    })->with([
        'a closure returning a static call' => ['unreadClosure'],
        'a closure returning a variable the gate rejects' => ['unreadVariable'],
        'a static call passed as it is' => ['unreadValue'],
    ]);

    test('a key only the skipped variable sets still publishes, optional, beside the keys the read branches agree on', function () {
        expect(signatureShape(MergedValueReadsResource::class, 'unreadValue'))
            ->toBe(['id' => 'number', 'extra_a' => 'number', 'title' => '?string']);
    });
});

describe('a docblock fill a reconcile put back survives for a later one', function () {
    test('a later key that replaces the unjoinable one lets the fill union', function () {
        expect(signatureShape(IndexSignatureConflictResource::class, 'laterKeyRestoresFill'))
            ->toMatchArray(['[key: `${string}_tag`]' => 'string | number | undefined', 'main_tag' => 'number']);
    });

    test('a method\'s own #[TsCasts] that types the unjoinable key lets the fill union', function () {
        expect(signatureShape(IndexSignatureConflictResource::class, 'castRestoresFill'))
            ->toMatchArray(['[key: `${string}_tag`]' => 'string | boolean | undefined', 'main_tag' => 'boolean']);
    });

    test('a fill beside a key that stays unjoinable is still put back', function () {
        $property = collect(new ResourceAstAnalyzer(new ReflectionClass(IndexSignatureConflictResource::class), Post::class, 'declinedKey')->analyze()->properties)
            ->firstWhere('name', '[key: `${string}_tag`]');

        expect($property)->toMatchArray(['type' => 'unknown | undefined', 'fillType' => 'string | undefined'])
            ->and($property)->not->toHaveKey('bodyType');
    });

    test('a branch\'s fill still unions after the branches merge into a type that is the fill itself', function () {
        expect(signatureShape(IndexSignatureConflictResource::class, 'branchFillBesideKey'))
            ->toMatchArray(['[key: `${string}_tag`]' => 'string | number | undefined', 'main_tag' => '?number']);
    });

    test('mergeReturnBranches() unions the fills beside the types, each branch\'s own value standing in for a missing one', function () {
        $branch = fn (string $type, array $extra = []): ResourceAnalysis => new ResourceAnalysis(properties: [[
            'name' => '[key: `${string}_tag`]',
            'type' => $type,
            'optional' => false,
            'description' => '',
            ...$extra,
        ]]);
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(PostResource::class), Post::class);

        $merged = $analyzer->mergeReturnBranches([
            $branch('unknown | undefined', ['fillType' => 'string | undefined']),
            $branch('number | undefined'),
        ]);
        $plain = $analyzer->mergeReturnBranches([$branch('string | undefined'), $branch('number | undefined')]);

        expect($merged->properties[0])->toMatchArray(['fillType' => 'string | undefined | number'])
            ->and($plain->properties[0])->not->toHaveKey('fillType');
    });

    test('a signature whose value names a model no generated file exports is still filled as a signature', function () {
        PublishedModelRegistry::register([Post::class]);
        $signature = fn (string $method): mixed => collect(new ResourceAstAnalyzer(new ReflectionClass(IndexSignatureConflictResource::class), Post::class, $method)->analyze()->properties)
            ->firstWhere('name', '[key: `${string}_tag`]');

        expect($signature('userTags'))->toMatchArray(['type' => 'string | undefined', 'fillType' => 'string | undefined'])
            ->and($signature('declinedUserTags'))->toMatchArray(['type' => 'unknown | undefined', 'fillType' => 'string | undefined']);
    });

    test('a spread helper\'s fill stands over the analyzed method\'s own docblock', function () {
        expect(signatureShape(IndexSignatureConflictResource::class, 'docblockOverHelperFill'))
            ->toMatchArray(['[key: `${string}_tag`]' => 'string | number | undefined', 'main_tag' => 'number']);
    });

    test('a signature entry written without a cast clears the cast an earlier entry of its name brought', function () {
        $analysis = new ResourceAstAnalyzer(new ReflectionClass(IndexSignatureConflictResource::class), Post::class, 'entryAfterCastSignature')->analyze();

        expect($analysis->casts)->not->toHaveKey('[key: `${string}_tag`]');
    });

    it('replaces a matched key\'s earlier arm when a method cast retypes it', function () {
        expect(signatureShape(IndexSignatureConflictResource::class, 'castReplacesUnionArm'))
            ->toMatchArray(['[key: `${string}_tag`]' => 'string | boolean | undefined', 'main_tag' => 'boolean']);
    });
});
