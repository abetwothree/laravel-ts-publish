<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\TsTypeString;

beforeEach(function () {
    $this->service = new TsTypeString;
});

describe('aliasPropertyType', function () {
    $nameMap = [
        'App\\Models\\User' => 'User',
        'Crm\\Models\\User' => 'User',
        'App\\Models\\Post' => 'Post',
        'App\\Models\\UserProfile' => 'UserProfile',
    ];

    test('replaces every occurrence when the item has one FQCN of that name', function () use ($nameMap) {
        expect($this->service->aliasPropertyType(
            'User[] | Record<string, User>',
            ['App\\Models\\User'],
            $nameMap,
            ['App\\Models\\User' => 'AppUser'],
        ))->toBe('AppUser[] | Record<string, AppUser>');
    });

    test('aliases each occurrence of a shared basename from its own FQCN', function () use ($nameMap) {
        expect($this->service->aliasPropertyType(
            'User | User',
            ['App\\Models\\User', 'Crm\\Models\\User'],
            $nameMap,
            ['App\\Models\\User' => 'AppUser', 'Crm\\Models\\User' => 'CrmUser'],
        ))->toBe('AppUser | CrmUser');
    });

    test('a repeated basename with more occurrences than FQCNs aliases every occurrence', function () use ($nameMap) {
        expect($this->service->aliasPropertyType(
            '{ a: User; b: User[]; c: Record<string, User> }',
            ['App\\Models\\User'],
            $nameMap,
            ['App\\Models\\User' => 'AppUser'],
        ))->toBe('{ a: AppUser; b: AppUser[]; c: Record<string, AppUser> }');
    });

    // The leftmost-occurrence heuristic this replaced left the trailing occurrence bare and unimportable.
    test('a repeated FQCN aliases every occurrence it owns', function () use ($nameMap) {
        expect($this->service->aliasPropertyType(
            '{ manager: User; primaryContact: User; secondaryContact: User }',
            ['App\\Models\\User', 'Crm\\Models\\User', 'Crm\\Models\\User'],
            $nameMap,
            ['App\\Models\\User' => 'AppUser', 'Crm\\Models\\User' => 'CrmUser'],
        ))->toBe('{ manager: AppUser; primaryContact: CrmUser; secondaryContact: CrmUser }');
    });

    // Deduping the list would collapse the queue to [Crm, App] and hold App for the third occurrence,
    // silently retyping a CRM user as an app user. Multiplicity, not the clamp, is what resolves this.
    test('a repeat that is not the last distinct FQCN still resolves to its own model', function () use ($nameMap) {
        expect($this->service->aliasPropertyType(
            '{ primaryContact: User; manager: User; secondaryContact: User }',
            ['Crm\\Models\\User', 'App\\Models\\User', 'Crm\\Models\\User'],
            $nameMap,
            ['App\\Models\\User' => 'AppUser', 'Crm\\Models\\User' => 'CrmUser'],
        ))->toBe('{ primaryContact: CrmUser; manager: AppUser; secondaryContact: CrmUser }');
    });

    // Two morph unions over the same targets in one type: occurrences run App, Crm, App, Crm. Neither
    // hold-the-last nor a cycling rule over a deduped queue gets this right; a per-occurrence list does.
    test('two same-basename unions in one type resolve arm by arm', function () use ($nameMap) {
        expect($this->service->aliasPropertyType(
            '{ a: Post | User | User; b: Post | User | User }',
            [
                'App\\Models\\Post', 'App\\Models\\User', 'Crm\\Models\\User',
                'App\\Models\\Post', 'App\\Models\\User', 'Crm\\Models\\User',
            ],
            $nameMap,
            ['App\\Models\\User' => 'AppUser', 'Crm\\Models\\User' => 'CrmUser'],
        ))->toBe('{ a: Post | AppUser | CrmUser; b: Post | AppUser | CrmUser }');
    });

    // Backstop only: when a caller cannot supply one entry per occurrence, the last FQCN covers the
    // overflow rather than leaving a bare token, which is what the unimportable-token gate depends on.
    test('the last FQCN covers occurrences past the end of a short queue', function () use ($nameMap) {
        expect($this->service->aliasPropertyType(
            'User | User | User',
            ['App\\Models\\User', 'Crm\\Models\\User'],
            $nameMap,
            ['App\\Models\\User' => 'AppUser', 'Crm\\Models\\User' => 'CrmUser'],
        ))->toBe('AppUser | CrmUser | CrmUser');
    });

    // A repeated FQCN would otherwise read as a collision and silently restore single replacement.
    test('a duplicated FQCN is not mistaken for a name collision', function () use ($nameMap) {
        expect($this->service->aliasPropertyType(
            'User[] | Record<string, User>',
            ['App\\Models\\User', 'App\\Models\\User'],
            $nameMap,
            ['App\\Models\\User' => 'AppUser'],
        ))->toBe('AppUser[] | Record<string, AppUser>');
    });

    test('a non-colliding sibling FQCN does not restrict the replacement', function () use ($nameMap) {
        expect($this->service->aliasPropertyType(
            'User[] | Record<string, User> | Post',
            ['App\\Models\\User', 'App\\Models\\Post'],
            $nameMap,
            ['App\\Models\\User' => 'AppUser'],
        ))->toBe('AppUser[] | Record<string, AppUser> | Post');
    });

    test('word boundaries keep longer identifiers intact', function () use ($nameMap) {
        expect($this->service->aliasPropertyType(
            'User | UserProfile | Users',
            ['App\\Models\\User'],
            $nameMap,
            ['App\\Models\\User' => 'AppUser'],
        ))->toBe('AppUser | UserProfile | Users');
    });

    // The trailing (?![A-Za-z0-9_$]) is what stops `User` claiming `UserProfile`: it fails on the `P` and
    // PCRE backtracks into the longer alternative. Passes with the longest-first usort deleted, so this
    // guards the outcome, not that sort — the sort is defensive only.
    test('a longer registered name is not shadowed by a shorter one that prefixes it', function () use ($nameMap) {
        expect($this->service->aliasPropertyType(
            'UserProfile | User',
            ['App\\Models\\User', 'App\\Models\\UserProfile'],
            $nameMap,
            ['App\\Models\\User' => 'AppUser', 'App\\Models\\UserProfile' => 'AppUserProfile'],
        ))->toBe('AppUserProfile | AppUser');
    });

    // Pick<>/Omit<> relation-filter references (e.g. `Pick<User, 'id' | 'user'>`) carry a bare model
    // token alongside lowercase quoted key literals — the quotes are valid word boundaries, so a key
    // that happens to spell the model name in lowercase must not be mistaken for the bare token.
    test('rewrites the bare model token inside Pick<>/Omit<> without touching quoted key literals', function () use ($nameMap) {
        expect($this->service->aliasPropertyType(
            "Pick<User, 'id' | 'user'>",
            ['App\\Models\\User'],
            $nameMap,
            ['App\\Models\\User' => 'AppUser'],
        ))->toBe("Pick<AppUser, 'id' | 'user'>");
    });

    test('an item with no mapped FQCN is returned untouched', function () use ($nameMap) {
        expect($this->service->aliasPropertyType('User | Post', ['App\\Models\\Team'], $nameMap, []))
            ->toBe('User | Post');
    });
});

describe('extractImportableTypes', function () {
    test('extractImportableTypes returns custom type names', function () {
        expect($this->service->extractImportableTypes('ProductMetadata'))
            ->toBe(['ProductMetadata']);
    });

    test('extractImportableTypes filters out primitives from union', function () {
        expect($this->service->extractImportableTypes('ProductMetadata | null'))
            ->toBe(['ProductMetadata']);
    });

    test('extractImportableTypes handles multiple custom types', function () {
        expect($this->service->extractImportableTypes('ProductMetadata | ProductJsonMetaData | null'))
            ->toBe(['ProductMetadata', 'ProductJsonMetaData']);
    });

    test('extractImportableTypes skips inline object types', function () {
        expect($this->service->extractImportableTypes('{ key: string } | null'))
            ->toBeEmpty();
    });

    test('extractImportableTypes skips tuple types', function () {
        expect($this->service->extractImportableTypes('[string, number] | null'))
            ->toBeEmpty();
    });

    test('extractImportableTypes skips generic types', function () {
        expect($this->service->extractImportableTypes('Array<string> | null'))
            ->toBeEmpty();
    });

    test('extractImportableTypes strips array shorthand', function () {
        expect($this->service->extractImportableTypes('MyType[]'))
            ->toBe(['MyType']);
    });

    test('extractImportableTypes deduplicates', function () {
        expect($this->service->extractImportableTypes('Foo | Foo | null'))
            ->toBe(['Foo']);
    });

    test('extractImportableTypes returns empty for all primitives', function () {
        expect($this->service->extractImportableTypes('string | number | boolean | null'))
            ->toBeEmpty();
    });
});

describe('TS_PRIMITIVES', function () {
    test('TS_PRIMITIVES contains all expected primitives', function () {
        expect(TsTypeString::TS_PRIMITIVES)->toContain(
            'string', 'number', 'boolean', 'bigint', 'symbol',
            'null', 'undefined', 'object', 'unknown', 'any', 'never', 'void',
        );
    });
});

describe('qualifyGlobalType', function () {
    test('resolves import alias to fully-qualified name (Pass 1)', function () {
        $result = $this->service->qualifyGlobalType(
            'CrmUser | null',
            ['crm.models' => ['User']],
            '',
            ['CrmUser' => 'crm.models.User'],
        );

        expect($result)->toBe('crm.models.User | null');
    });

    test('uses bare name when alias target is in the skip namespace (Pass 1)', function () {
        $result = $this->service->qualifyGlobalType(
            'CrmUser | null',
            ['crm.models' => ['User']],
            'crm.models',
            ['CrmUser' => 'crm.models.User'],
        );

        expect($result)->toBe('User | null');
    });

    test('qualifies a bare type name with its namespace prefix (Pass 2)', function () {
        $result = $this->service->qualifyGlobalType(
            'User | null',
            ['app.models' => ['User', 'Post']],
            '',
        );

        expect($result)->toBe('app.models.User | null');
    });

    test('skips qualification for types in the skip namespace (Pass 2)', function () {
        $result = $this->service->qualifyGlobalType(
            'User | Post',
            ['app.models' => ['User', 'Post']],
            'app.models',
        );

        expect($result)->toBe('User | Post');
    });

    test('matches longer names first to prevent partial replacement', function () {
        $result = $this->service->qualifyGlobalType(
            'StatusType | Status',
            ['enums' => ['Status', 'StatusType']],
            '',
        );

        expect($result)->toBe('enums.StatusType | enums.Status');
    });

    test('does not re-qualify already-qualified types', function () {
        $result = $this->service->qualifyGlobalType(
            'crm.models.User | null',
            ['app.models' => ['User']],
            '',
        );

        expect($result)->toBe('crm.models.User | null');
    });

    test('does not re-qualify bare names that belong to the skip namespace', function () {
        // After Pass 1, AppUser becomes bare 'User'; Pass 2 must not re-qualify it with crm.models.
        $result = $this->service->qualifyGlobalType(
            'Post | Product | AppUser | CrmUser',
            ['app.models' => ['User', 'Post', 'Product'], 'crm.models' => ['User']],
            'app.models',
            ['AppUser' => 'app.models.User', 'CrmUser' => 'crm.models.User'],
        );

        expect($result)->toBe('Post | Product | User | crm.models.User');
    });
});

describe('rewriteAsEnumToType', function () {
    test('replaces AsEnum<typeof X> with the qualified type alias', function () {
        $result = $this->service->rewriteAsEnumToType(
            'AsEnum<typeof Status>',
            ['Status' => 'enums.StatusType'],
        );

        expect($result)->toBe('enums.StatusType');
    });

    test('preserves surrounding null union after replacement', function () {
        $result = $this->service->rewriteAsEnumToType(
            'AsEnum<typeof Status> | null',
            ['Status' => 'enums.StatusType'],
        );

        expect($result)->toBe('enums.StatusType | null');
    });

    test('replaces multiple AsEnum patterns in a single string', function () {
        $result = $this->service->rewriteAsEnumToType(
            'AsEnum<typeof Status> | AsEnum<typeof Priority>',
            ['Status' => 'enums.StatusType', 'Priority' => 'enums.PriorityType'],
        );

        expect($result)->toBe('enums.StatusType | enums.PriorityType');
    });

    test('leaves unknown const aliases unchanged', function () {
        $result = $this->service->rewriteAsEnumToType(
            'AsEnum<typeof Unknown> | null',
            ['Status' => 'enums.StatusType'],
        );

        expect($result)->toBe('AsEnum<typeof Unknown> | null');
    });

    test('handles extra whitespace inside typeof', function () {
        $result = $this->service->rewriteAsEnumToType(
            'AsEnum<typeof  Status  > | null',
            ['Status' => 'enums.StatusType'],
        );

        expect($result)->toBe('enums.StatusType | null');
    });

    test('collapses an adjacent AsEnum<typeof X> | XType pair to one qualified token', function () {
        $result = $this->service->rewriteAsEnumToType(
            'AsEnum<typeof Status> | StatusType',
            ['Status' => 'enums.StatusType'],
        );

        expect($result)->toBe('enums.StatusType');
    });

    test('collapses the mixed pair and keeps a trailing null arm', function () {
        $result = $this->service->rewriteAsEnumToType(
            'AsEnum<typeof Priority> | PriorityType | null',
            ['Priority' => 'enums.PriorityType'],
        );

        expect($result)->toBe('enums.PriorityType | null');
    });

    test('does not collapse a bare type name that only coincidentally shares a prefix', function () {
        $result = $this->service->rewriteAsEnumToType(
            'AsEnum<typeof Status> | StatusTypeExtra',
            ['Status' => 'enums.StatusType'],
        );

        expect($result)->toBe('enums.StatusType | StatusTypeExtra');
    });

    test('folds the reversed pair ordering', function () {
        $result = $this->service->rewriteAsEnumToType(
            'StatusType | AsEnum<typeof Status>',
            ['Status' => 'app.enums.StatusType'],
        );

        expect($result)->toBe('app.enums.StatusType');
    });

    test('does not fold a reversed pair when the bare name is part of a longer identifier', function () {
        $result = $this->service->rewriteAsEnumToType(
            'MyStatusType | AsEnum<typeof Status>',
            ['Status' => 'app.enums.StatusType'],
        );

        expect($result)->toBe('MyStatusType | app.enums.StatusType');
    });

    test('does not fold a reversed pair when the bare name is already namespace-qualified', function () {
        $result = $this->service->rewriteAsEnumToType(
            'foo.StatusType | AsEnum<typeof Status>',
            ['Status' => 'app.enums.StatusType'],
        );

        expect($result)->toBe('foo.StatusType | app.enums.StatusType');
    });

    // A heterogeneous mixed union — ResourceTransformer::rewriteEnumResourceTypes()'s isCollection
    // branch — is not the redundant same-shaped pair the fold exists for: the trailing
    // `[]` makes the two members genuinely different, so both must survive, not collapse to one.
    test('does not fold a pair whose bare arm is itself array-shaped', function () {
        $result = $this->service->rewriteAsEnumToType(
            'AsEnum<typeof Status> | StatusType[]',
            ['Status' => 'enums.StatusType'],
        );

        expect($result)->toBe('enums.StatusType | StatusType[]');
    });
});

describe('splitTopLevelUnion', function () {
    it('splits only at depth zero', function () {
        expect($this->service->splitTopLevelUnion('{ a: string; b: number | null } | null'))
            ->toBe(['{ a: string; b: number | null }', 'null']);
    });

    it('keeps a union inside an array element type whole', function () {
        expect($this->service->splitTopLevelUnion('(string | null)[]'))
            ->toBe(['(string | null)[]']);
    });

    it('keeps a union inside a generic whole', function () {
        expect($this->service->splitTopLevelUnion('Record<string, number | null>'))
            ->toBe(['Record<string, number | null>']);
    });

    it('splits quoted literals and ignores pipes inside them', function () {
        expect($this->service->splitTopLevelUnion("'a|b' | 'c'"))
            ->toBe(["'a|b'", "'c'"]);
    });

    it('keeps a double-quoted literal whole', function () {
        expect($this->service->splitTopLevelUnion('{ theme: "light" | "dark" } | null'))
            ->toBe(['{ theme: "light" | "dark" }', 'null']);
    });
});

describe('hoistNull', function () {
    it('hoists every top-level null to one trailing member', function (array $types, string $expected) {
        expect($this->service->hoistNull($types))->toBe($expected);
    })->with([
        'two nullable arms' => [['string | null', 'number | null'], 'string | number | null'],
        'null arm only' => [['{ a: string | null }', 'null'], '{ a: string | null } | null'],
        'no null' => [['string', 'number'], 'string | number'],
        'duplicate member across arms' => [['A | null', 'A'], 'A | null'],
    ]);
});

describe('typeNameOccursIn', function () {
    test('typeNameOccursIn() matches a bare type token and not a member access or a longer identifier', function () {
        $s = $this->service;
        expect($s->typeNameOccursIn('StatusType', 'StatusType | null'))->toBeTrue()
            ->and($s->typeNameOccursIn('StatusType', '{ a: StatusType[] }'))->toBeTrue()
            ->and($s->typeNameOccursIn('StatusType', 'foo.StatusType'))->toBeFalse()
            ->and($s->typeNameOccursIn('StatusType', '$StatusType'))->toBeFalse()
            ->and($s->typeNameOccursIn('StatusType', 'CrmStatusType'))->toBeFalse()
            ->and($s->typeNameOccursIn('StatusType', 'StatusTypeExtra'))->toBeFalse();
    });
});

describe('isVagueTsType', function () {
    test('a bare object is vague', function () {
        expect($this->service->isVagueTsType('object'))->toBeTrue();
    });

    test('an unknown outside a shape is vague', function () {
        expect($this->service->isVagueTsType('unknown'))->toBeTrue()
            ->and($this->service->isVagueTsType('unknown[]'))->toBeTrue()
            ->and($this->service->isVagueTsType('Record<string, unknown>'))->toBeTrue();
    });

    test('an unknown inside an object literal is not vague', function () {
        expect($this->service->isVagueTsType('{ a: unknown }'))->toBeFalse()
            ->and($this->service->isVagueTsType('{ a: unknown } | null'))->toBeFalse();
    });

    test('an ordinary type is not vague', function () {
        expect($this->service->isVagueTsType('string'))->toBeFalse()
            ->and($this->service->isVagueTsType('User[] | null'))->toBeFalse()
            ->and($this->service->isVagueTsType('Record<string, string>'))->toBeFalse();
    });
});
