<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Members\MethodReturnTypeResolver;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\AppendedWrapDelegatedResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\AppendedWrapMergeResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\AppendedWrapParentResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\AppendedWrapSpreadResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HelperCaseGetterResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\WrappedPriorityPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AppendedPriorityWrapPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AuthoredPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\DocumentedGetterPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\EnumCaseGetterModel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\EnumShapePost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\IdiomPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\TwoStatusPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\WrappedPriorityPost;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\FilteringAccessorModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReadOrderAccessorModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\UntypedFilterOverrideModel;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use Workbench\App\Enums\Status;
use Workbench\App\Models\BulletinArchive;
use Workbench\App\Models\BulletinBoard;
use Workbench\App\Models\BulletinFeed;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Image;
use Workbench\App\Models\Release;
use Workbench\App\Models\User;
use Workbench\Crm\Enums\Status as CrmStatus;

describe('AccessorBodyAnalyzer through ModelAttributeResolver', function () {
    test('types accessors from their getter bodies', function (string $attribute, string $expected) {
        expect(resolve(ModelAttributeResolver::class)->resolveAttribute(Release::class, $attribute)['type'])->toBe($expected);
    })->with([
        ['version_data', '{ major: number; minor: number }'],
        ['label', '{ full: string; notes: string | null }'],
        ['tag_list', '{ name: string }[]'],
        ['channels', 'string[]'],
        ['summary', '{ major: number }'],
        ['dynamic_totals', '{ [key: `k${string}`]: unknown | undefined }'],
        ['length_callable', 'Record<string, never>'],
        ['key_callable', 'Record<string, never>'],
        ['checked_at', 'string'],
        ['channel_ids', 'number[]'],
    ]);

    test('a getter that builds its array by key writes sends the object those keys type', function () {
        expect((new Release)->dynamic_totals)->toBe(['k1' => 1, 'k2' => 2])
            ->and(json_encode((new Release)->dynamic_totals))->toBe('{"k1":1,"k2":2}');
    });

    test('keys a constant-keyed getter body by the constant values', function () {
        expect(resolve(ModelAttributeResolver::class)->resolveAttribute(Release::class, 'channel_options')['type'])
            ->toBe('{ "1": string; "2": string }');
    });

    test('an empty literal body is declined, so the getter signature still answers', function () {
        // `[]` resolves to never[] — non-vague, but holding nothing. Publishing it would override a
        // better annotation, so the body step declines and the vague `: array` answers as before.
        expect(resolve(ModelAttributeResolver::class)->resolveAttribute(Release::class, 'empty_list')['type'])
            ->toBe('unknown[]');
    });

    test('reads the getter off the new Attribute(get: ...) constructor form', function () {
        expect(resolve(ModelAttributeResolver::class)->resolveAttribute(Release::class, 'constructed_version')['type'])
            ->toBe('{ major: number }');
    });

    test('a trait-declared accessor resolves $this against the model that uses the trait', function () {
        // The body lives in DerivesReleaseVersion's own file; `major` is a column on releases, so it
        // only resolves because analyzeModelClosure() puts the model class on the scope.
        expect(resolve(ModelAttributeResolver::class)->resolveAttribute(Release::class, 'trait_version')['type'])
            ->toBe('{ major: number; label: string }');
    });

    test('a cycle between two accessors terminates as unknown', function () {
        expect(resolve(ModelAttributeResolver::class)->resolveAttribute(Release::class, 'loop_a')['type'])->toBe('unknown');
    });
});

describe('AccessorBodyAnalyzer for a getter a method body reads', function () {
    $published = [
        'own_picks' => ["{ v: Pick<FilteringAccessorModel, 'id' | 'title'>; id: number }", [FilteringAccessorModel::class]],
        'own_runtime' => ['{ v: Record<string, unknown>; id: number }', []],
        'own_nullsafe' => ["{ v: Pick<FilteringAccessorModel, 'id' | 'content'>; id: number }", [FilteringAccessorModel::class]],
        'author_picks' => ["{ v: Pick<User, 'id' | 'role'> | null; id: number }", [User::class]],
        'comment_picks' => ['{ v: Comment[]; id: number }', [Comment::class]],
        'comment_list' => ['Comment[]', [Comment::class]],
        'tagged_fields' => ['{ id: number }[]', []],
        'doc_records' => ['Comment[]', [Comment::class]],
        'doc_records_nullsafe' => ['Comment[] | null', [Comment::class]],
        'doc_class_list' => ['Comment[]', [Comment::class]],
        'doc_keyed' => ['Comment[]', [Comment::class]],
        'doc_int_mixed' => ['Comment[]', [Comment::class]],
        'doc_record_or_list' => ['Comment[]', [Comment::class]],
        'doc_nested_records' => ['Comment[][]', [Comment::class]],
        'signed_tag_rows' => ['Comment[]', [Comment::class]],
        'legacy_tag_rows' => ['Comment[]', [Comment::class]],
        'loose_picks' => ["{ v: Pick<UntypedFilterOverrideModel, 'id' | 'title'>; id: number }", [UntypedFilterOverrideModel::class]],
        'counterpart_picks' => ["{ v: Pick<Comment, 'id'> | Pick<User, 'id'> | null; id: number }", [Comment::class, User::class]],
        'loop_a' => ["{ v: { v: unknown; p: Pick<User, 'id'> }; p: Pick<User, 'id'> }", [User::class]],
    ];

    $readers = [
        'readOwnPicks', 'readOwnRuntime', 'readOwnNullsafe', 'readAuthorPicks', 'readCommentPicks', 'readCommentList',
        'readTaggedFields', 'readLoosePicks', 'readCounterpartPicks', 'readLoop', 'readDocRecords', 'readDocRecordsNullsafe',
        'readDocClassList', 'readSignedTagRows', 'readLegacyTagRows', 'readDocKeyed', 'readDocIntMixed', 'readDocRecordOrList',
        'readDocNestedRecords',
    ];

    // The model file publishes the getter's own analysis; a method body's read of it must not replace it.
    test('the accessor keeps its published type and imports after a method body reads it', function () use ($published, $readers) {
        foreach ($readers as $reader) {
            resolve(MethodReturnTypeResolver::class)->resolve(FilteringAccessorModel::class, $reader);
        }

        $mutators = (new ModelTransformer(FilteringAccessorModel::class))->data()->mutators;

        foreach ($published as $attribute => [$type, $classFqcns]) {
            $resolved = resolve(ModelAttributeResolver::class)->resolveAttribute(FilteringAccessorModel::class, $attribute);

            expect($resolved['type'])->toBe($type)
                ->and($resolved['classFqcns'])->toBe($classFqcns)
                ->and($mutators[$attribute]['type'])->toBe($type);
        }
    });

    test('a method body reads the accessor as the model publishes it, after the model publishes it', function () use ($published) {
        foreach (array_keys($published) as $attribute) {
            resolve(ModelAttributeResolver::class)->resolveAttribute(FilteringAccessorModel::class, $attribute);
        }

        expect(resolve(MethodReturnTypeResolver::class)->resolve(FilteringAccessorModel::class, 'readAuthorPicks')['type'] ?? null)
            ->toBe("{ v: { v: Pick<User, 'id' | 'role'> | null; id: number }; id: number }")
            ->and(resolve(MethodReturnTypeResolver::class)->resolve(FilteringAccessorModel::class, 'readCommentList')['type'] ?? null)
            ->toBe('{ v: Comment[]; id: number }');
    });

    test('a cycle between two accessors terminates when a method body reads it', function () {
        expect(resolve(MethodReturnTypeResolver::class)->resolve(FilteringAccessorModel::class, 'readLoop')['type'] ?? null)
            ->toBe("{ v: { v: { v: unknown; p: Pick<User, 'id'> }; p: Pick<User, 'id'> }; id: number }");
    });

    // The getter calls report(), whose body reads the getter back in a reading of its own, as the import-less read is:
    // each keeps its own guard, so no read cuts another short and no answer depends on who asked first.
    test('a getter, the method body reading it back and its import-less read each publish one answer in any order', function (string $first) {
        $reads = [
            'getter' => fn () => resolve(ModelAttributeResolver::class)->resolveAttribute(FilteringAccessorModel::class, 'self_report')['type'],
            'method' => fn () => resolve(MethodReturnTypeResolver::class)->resolve(FilteringAccessorModel::class, 'report')['type'] ?? null,
            'import-less' => fn () => resolve(ModelAttributeResolver::class)->resolveAttribute(FilteringAccessorModel::class, 'self_report', false)['type'],
        ];
        $report = "{ self: { v: Pick<User, 'id'>; report: Record<string, unknown> }; id: number }";

        $types = [$first => $reads[$first]()];

        foreach ($reads as $name => $read) {
            $types[$name] ??= $read();
        }

        expect($types['getter'])->toBe("{ v: Pick<User, 'id'>; report: $report }")
            ->and($types['method'])->toBe($report)
            ->and($types['import-less'])->toBe("{ v: { id: number }; report: $report }");
    })->with(['getter first' => 'getter', 'method first' => 'method', 'an import-less read first' => 'import-less']);

    // listing() reads the accessor back without imports, and that read's tie-break needs the accessor's analysis with
    // imports, which a model read is already running: the method's shape must not depend on which was read first.
    test('a method reading back an accessor whose import-less spelling is vague publishes one shape in any read order', function (string $first) {
        $reads = [
            'model' => fn () => resolve(ModelAttributeResolver::class)->resolveAttribute(ReadOrderAccessorModel::class, 'listed_comments')['type'],
            'method' => fn () => resolve(MethodReturnTypeResolver::class)->resolve(ReadOrderAccessorModel::class, 'listing')['type'] ?? null,
            'import-less' => fn () => resolve(ModelAttributeResolver::class)->resolveAttribute(ReadOrderAccessorModel::class, 'listed_comments', false)['type'],
        ];

        $types = [$first => $reads[$first]()];

        foreach ($reads as $name => $read) {
            $types[$name] ??= $read();
        }

        expect($types)->toMatchArray([
            'model' => 'Comment[]',
            'method' => '{ v: Comment[]; id: number }',
            'import-less' => 'unknown[]',
        ]);
    })->with(['model first' => 'model', 'method first' => 'method', 'an import-less read first' => 'import-less']);
});

// The `@return` before a getter closure answers where its body types nothing, as for a resource's value closure.
// A getter reads a vague helper's case as the literal it sends, while a wrap or a map callback reading the attribute
// needs, and gets, the case's enum through the getter and the helper.
test('a getter returning a vague helper\'s case types its literal, and its enum where a wrap or a map reads it', function () {
    expect(resourceProps(HelperCaseGetterResource::class))->toBe([
        'held' => '0',
        'held_resource' => 'AsEnum<typeof Status>',
        'held_old_resource' => 'AsEnum<typeof Status>',
        'held_values' => 'StatusType[]',
    ]);
});

describe('AccessorBodyAnalyzer for a getter its closure\'s docblock types', function () {
    test('the model publishes each getter\'s documented type, passed by name or by position', function () {
        expect(array_map(fn (array $mutator): string => $mutator['type'], (new ModelTransformer(DocumentedGetterPost::class))->data()->mutators))
            ->toEqual([
                'doc_label' => 'string',
                'doc_count' => 'number',
                'doc_post' => 'Post | null',
                'doc_status' => 'StatusType',
            ]);
    });

    test('a method body reads a getter documented as a class as the model publishes it', function () {
        $read = fn (string $method): ?string => resolve(MethodReturnTypeResolver::class)->resolve(DocumentedGetterPost::class, $method)['type'] ?? null;

        expect($read('readDocumented'))->toBe('{ label: string; post: Post | null; status: StatusType; id: number }')
            ->and($read('readPicked'))->toBe('{ v: { id: number; doc_count: number; doc_post: Post | null; doc_status: StatusType }; id: number }');
    });
});

describe('AccessorBodyAnalyzer for a getter reading another model\'s accessor', function () {
    // Each read names a class only through the accessor it reads, so the file imports it only if the read carries it.
    test('the read carries the class the accessor names, and the model file imports it', function (string $model, string $attribute, string $type, array $classFqcns) {
        $resolved = resolve(ModelAttributeResolver::class)->resolveAttribute($model, $attribute);
        $data = (new ModelTransformer($model))->data();

        expect($resolved['type'])->toBe($type)
            ->and($resolved['classFqcns'])->toBe($classFqcns)
            ->and($data->mutators[$attribute]['type'])->toBe($type)
            ->and($data->typeImports['.'] ?? [])->toContain(...array_map(class_basename(...), $classFqcns));
    })->with([
        'a typed closure parameter' => [BulletinBoard::class, 'comment_lists', 'Comment[][]', [Comment::class]],
        'a nullsafe relation chain' => [BulletinBoard::class, 'lead_author', "Pick<User, 'id' | 'name'> | null", [User::class]],
        'a relation chain' => [BulletinFeed::class, 'lead_comments', 'Comment[]', [Comment::class]],
        'an untyped closure parameter over a docblock accessor' => [BulletinFeed::class, 'owners', 'User[]', [User::class]],
        'pluck()' => [BulletinArchive::class, 'comment_lists', 'Comment[][]', [Comment::class]],
        'a shape a closure parameter builds' => [BulletinArchive::class, 'author_rows', "{ author: Pick<User, 'id' | 'name'> }[]", [User::class]],
    ]);

    test('a method body making the same reads carries the classes they name', function () {
        $resolved = resolve(MethodReturnTypeResolver::class)->resolve(BulletinBoard::class, 'summary');

        expect($resolved)->toBe([
            'type' => "{ comment_lists: Comment[][]; lead_author: Pick<User, 'id' | 'name'> | null; id: number }",
            'optional' => false,
            'keepsKeys' => true,
            'embeddedModelFqcns' => [Comment::class, User::class],
        ]);
    });
});

describe('AccessorBodyAnalyzer declines a body type that names nothing it can publish', function () {
    // Each getter drops an untypable arm, and the runtime value is that arm's: 'Hello', {a: 1}, a decrypted string.
    test('a bare null left once the untypable arm is dropped is not a type', function (string $attribute) {
        expect(resolve(ModelAttributeResolver::class)->resolveAttribute(IdiomPost::class, $attribute)['type'])->toBe('unknown');
    })->with(['nickname', 'decoded_meta', 'secret_note', 'meta_title', 'legacy_payload']);

    test('a real arm left beside null stays, as does a getter that only ever returns null', function () {
        expect(resolve(ModelAttributeResolver::class)->resolveAttribute(IdiomPost::class, 'title_or_null')['type'])->toBe('string | null')
            ->and(resolve(ModelAttributeResolver::class)->resolveAttribute(Image::class, 'no_docblock_accessor')['type'])->toBe('null');
    });

    test('two models sharing a name publish under a token each, in a union as under separate keys', function (string $accessor, string $type) {
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

        expect((new ModelTransformer(AuthoredPost::class))->data()->mutators[$accessor]['type'])->toBe($type);
    })->with([
        'a union' => ['author_or_lead', 'AuthorUser | CrmAuthorUser'],
        'a union a `??` falls through to, past an operand the engine cannot type' => [
            'cached_author_or_lead',
            'AuthorUser | CrmAuthorUser',
        ],
        'separate keys' => ['both_authors', '{ author: AuthorUser; lead: CrmAuthorUser }'],
        'a key naming one model beside a key naming the union' => [
            'author_and_either',
            '{ author: AuthorUser; either: AuthorUser | CrmAuthorUser }',
        ],
        'a model queued twice behind one token, before a key naming the other model' => [
            'lead_or_label_and_author',
            '{ lead: CrmAuthorUser | string; author: AuthorUser }',
        ],
    ]);

    test('a resource the body returns declines, since the model file cannot import it', function (string $attribute) {
        expect(resolve(ModelAttributeResolver::class)->resolveAttribute(AuthoredPost::class, $attribute)['type'])->toBe('unknown');
    })->with(['author_resource', 'author_to_resource']);

    test('two enums sharing a name publish under a token each, as a native union of them does', function () {
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

        $resolved = resolve(ModelAttributeResolver::class)->resolveAttribute(TwoStatusPost::class, 'status_or_lead');

        expect($resolved['type'])->toBe('StatusType | StatusType | null')
            ->and($resolved['enumFqcns'])->toBe([Status::class, CrmStatus::class])
            ->and((new ModelTransformer(TwoStatusPost::class))->data()->mutators['status_or_lead']['type'])
            ->toBe('EnumsStatusType | CrmStatusType | null');
    });

    test('an untyped getter returning a case publishes its enum', function () {
        expect(array_map(fn (array $mutator): string => $mutator['type'], (new ModelTransformer(EnumCaseGetterModel::class))->data()->mutators))
            ->toBe([
                'default_state' => 'WorkbenchStatusType',
                'either_state' => 'WorkbenchStatusType | CrmStatusType',
                'state_shape' => '{ s: WorkbenchStatusType; n: number }',
            ]);
    });

    test('a shape, list or union around an enum keeps its own type in the model file', function (string $attribute, string $type) {
        expect((new ModelTransformer(EnumShapePost::class))->data()->mutators[$attribute]['type'])->toBe($type);
    })->with([
        'a shape' => ['badge', '{ status: StatusType; label: string }'],
        'a list of shapes' => ['state_list', '{ id: number; state: StatusType }[]'],
        'a union with another arm' => ['state_or_none', 'StatusType | string'],
        'the enum alone' => ['plain_state', 'StatusType'],
    ]);
});

// filter() sends a wrap an accessor builds as null where a resource's value reaches it, though the model's own JSON,
// and a map kept as a Collection, serialize it as the `[]` resolve() returns.
test('an accessor\'s EnumResource wrap publishes what its reader sends for a null enum', function () {
    $post = (new WrappedPriorityPost)->forceFill(['id' => 2, 'priority' => null]);

    expect(publishedResourceKeyTypes(WrappedPriorityPostResource::class, WrappedPriorityPost::class))->toBe([
        'accessor' => 'PriorityType | null',
        'accessor_kept' => '(PriorityType | never[])[]',
        'accessor_all' => '(PriorityType | null)[]',
    ])
        ->and((string) new WrappedPriorityPostResource($post)->response(request())->getContent())
        ->toBe('{"data":{"accessor":null,"accessor_kept":[[]],"accessor_all":[null]}}')
        ->and(new ModelTransformer(WrappedPriorityPost::class)->data()->mutators['priority_wrap']['type'])
        ->toBe('PriorityType | never[]')
        ->and(json_encode($post->append('priority_wrap')->only('priority_wrap')))->toBe('{"priority_wrap":[]}');
});

// A resource sending its model's own array, whole, spread or merged, hands each appended wrap to filter(), which sends
// it as null, though the model's own JSON sends `[]`.
test('an appended accessor\'s EnumResource wrap publishes the null filter() sends through the model\'s own array', function (string $resource) {
    $post = (new AppendedPriorityWrapPost)->forceFill(['id' => 2, 'priority' => null]);

    expect(publishedResourceKeyTypes($resource, AppendedPriorityWrapPost::class)['priority_wrap'])->toBe('PriorityType | null')
        ->and((string) new $resource($post)->response(request())->getContent())->toContain('"priority_wrap":null')
        ->and(json_encode($post))->toBe('{"id":2,"priority":null,"priority_wrap":[]}');
})->with([
    'parent::toArray() returned whole' => [AppendedWrapParentResource::class],
    'parent::toArray() spread' => [AppendedWrapSpreadResource::class],
    'the model merged' => [AppendedWrapMergeResource::class],
    'no toArray() of its own' => [AppendedWrapDelegatedResource::class],
]);
