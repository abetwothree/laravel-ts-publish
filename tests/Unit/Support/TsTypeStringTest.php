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
            ->and($s->typeNameOccursIn('StatusType', 'foo.bar.StatusType'))->toBeFalse()
            ->and($s->typeNameOccursIn('StatusType', 'StatusType.foo'))->toBeTrue()
            ->and($s->typeNameOccursIn('StatusType', '$StatusType'))->toBeFalse()
            ->and($s->typeNameOccursIn('StatusType', '_StatusType'))->toBeFalse()
            ->and($s->typeNameOccursIn('StatusType', 'CrmStatusType'))->toBeFalse()
            ->and($s->typeNameOccursIn('StatusType', 'StatusTypeExtra'))->toBeFalse()
            ->and($s->typeNameOccursIn('StatusType', 'StatusType2'))->toBeFalse()
            ->and($s->typeNameOccursIn('StatusType', 'StatusType$'))->toBeFalse();
    });

    test('typeNameOccursIn() reads every boundary TypeScript reads as a type reference', function (string $haystack, bool $found) {
        expect($this->service->typeNameOccursIn('User', $haystack))->toBe($found);
    })->with([
        'a type query' => ['typeof User', true],
        'a keyof' => ['keyof User', true],
        'an array' => ['User[]', true],
        'a generic' => ['User<string>', true],
        'a type argument' => ['Array<User>', true],
        'a union arm' => ['string | User | null', true],
        'parenthesized' => ['(User)', true],
        'an object member' => ['{ k: User }', true],
        'a tuple element' => ['[User]', true],
        'a conditional check' => ['User extends Post ? 1 : 0', true],
        'an inferred name' => ['Post extends infer User ? 1 : 0', true],
        'a variadic tuple tail' => ['[string, ...User[]]', true],
        'a variadic tuple head' => ['[...User[], string]', true],
        'a rest element without brackets' => ['[string, ...User]', true],
        'a readonly variadic tuple' => ['readonly [...User[]]', true],
        'a variadic tuple inside an object' => ['{ a: [...User[]] }', true],
        'a spread inside braces' => ['{...User}', true],
        'a labeled rest element' => ['[a: string, ...rest: User[]]', true],
        'a spread with a space' => ['[string, ... User[]]', true],
        // TypeScript reads `1User` as a number then the name; a digit inside a longer identifier is not a boundary
        // this match knows, so `a1User` then costs at most an unused import.
        'a name right after a numeric literal' => ['1User', true],
        'a name after a digit inside a longer identifier' => ['a1User', true],
        'a name after a numeric literal and a dot' => ['1.User', true],
        // Nor is a non-ASCII identifier character, for the same reason.
        'a name after a non-ASCII letter' => ["\u{e9}User", true],
        'a name before a non-ASCII letter' => ["User\u{e9}", true],
        'a member of a namespace' => ['Models.User', false],
        'a member of a nested namespace' => ['App.Models.User', false],
        'a member of an imported module' => ["import('x').User", true],
        'a member of the type' => ['User.Inner', true],
        'a dollar-prefixed identifier' => ['$User', false],
        'an underscore-prefixed identifier' => ['_User', false],
        'a dollar-suffixed identifier' => ['User$', false],
    ]);

    // The fallback's accepted cost: an override that spells an imported name inside a string, template text or comment
    // keeps that import unused, where a lexer that hid the name from the prune dropped an import the file needed.
    test('typeNameOccursIn() counts a name spelled inside a string literal too', function () {
        $s = $this->service;
        expect($s->typeNameOccursIn('User', "'User' | 'Admin'"))->toBeTrue()
            ->and($s->typeNameOccursIn('User', '{ "User": string }'))->toBeTrue()
            ->and($s->typeNameOccursIn('User', "'it\\'s User'"))->toBeTrue()
            ->and($s->typeNameOccursIn('User', "Pick<User, 'id' | 'name'>"))->toBeTrue()
            ->and($s->typeNameOccursIn('User', "'User' | User"))->toBeTrue();
    });

    test('typeNameOccursIn() reads a template literal\'s placeholders and its text alike', function (string $haystack, string $name, bool $found) {
        expect($this->service->typeNameOccursIn($name, $haystack))->toBe($found);
    })->with([
        'an apostrophe, then a model' => ["`\${string}'s label`\nPick<User, 'id' | 'name'> | null", 'User', true],
        'an apostrophe, then an enum' => ["`\${string}'s label`\nStatusType | null\nPick<User, 'id'> | null", 'StatusType', true],
        'an apostrophe, then a #[TsType] class' => ["`\${string}'s label`\nMenuSettingsType | null", 'MenuSettingsType', true],
        'feet and inches' => ["`\${number}'\${number}\"`\nPick<User, 'id' | 'name'> | null", 'User', true],
        'an inch mark, then a quoted key' => ["`\${number}\"`\n{ \"a-b\": User | null }\n{ \"c-d\": number }", 'User', true],
        'an escaped single quote' => ["'it\\'s' | 'b'\nUser", 'User', true],
        'an escaped double quote' => ["\"say \\\"hi\\\"\" | \"b\"\nUser", 'User', true],
        'no quote at all' => ["`\${string}-label`\nUser", 'User', true],
        'a placeholder names the type' => ['`${Priority}-label`', 'Priority', true],
        // The accepted cost, in a placeholder's string and in a template's text.
        'a quoted literal inside a placeholder' => ["`\${'User' | 'Admin'}-label`", 'User', true],
        'the literal text names it' => ['`User-${string}`', 'User', true],
        'a template literal that never closes' => ["`\${string}'s label\nUser", 'User', true],
        'a literal that never closes, before the name on its line' => ["`\${string}'s label | User", 'User', true],
    ]);

    // The reviewer's 30 lexing cases. Every name TypeScript reads is counted; the five it reads as text count as well,
    // which is the accepted cost.
    test('typeNameOccursIn() counts a name whatever literal or comment surrounds it', function (string $haystack, string $name, bool $found) {
        expect($this->service->typeNameOccursIn($name, $haystack))->toBe($found);
    })->with([
        'nested template, name in inner placeholder' => ['`${`a${User}`}`', 'User', true],
        'nested template, then name' => ['`${`a${string}`}` | User', 'User', true],
        'nested template text names it' => ['`${`User ${string}`}`', 'User', true],
        'quote inside placeholder, then name in it' => ["`\${'x' | User}`", 'User', true],
        'brace inside a string in a placeholder' => ['`${"}"}` | User', 'User', true],
        'backtick literal inside placeholder' => ['`${`}`}` | User', 'User', true],
        '${ inside single-quoted string, then name' => ["'\${' | User", 'User', true],
        '${ inside double-quoted string, then name' => ['"${" | User', 'User', true],
        'name inside ${…} of a single-quoted string' => ["'\${User}'", 'User', true],
        'unterminated single quote before name' => ["'abc | User", 'User', true],
        'unterminated template before name' => ['`abc | User', 'User', true],
        'unterminated placeholder' => ['`${User', 'User', true],
        'apostrophe in template then name' => ["`\${string}'s` | User", 'User', true],
        'escaped quote in single' => ["'it\\'s' | User", 'User', true],
        'escaped quote in double' => ['"a\\"b" | User', 'User', true],
        'escaped backtick in template' => ['`a\\`b` | User', 'User', true],
        'escaped placeholder is text' => ['`\\${User}`', 'User', true],
        'backtick inside quoted string' => ["'`' | User", 'User', true],
        'empty placeholder' => ['`${}` | User', 'User', true],
        'object braces inside placeholder' => ['`${ {a: User}["a"] }`', 'User', true],
        'a name after object braces inside a placeholder' => ['`${ {a: string}["a"] | User }`', 'User', true],
        'multi-line: next properties on own lines' => ["`\${string}'s`\nUser", 'User', true],
        'multi-line template, closing line then name then template' => ["`line one\nline two` | User | `x`", 'User', true],
        'multi-line template, closing line then name then quote' => ["`line one\nit's` | User | 'x'", 'User', true],
        'multi-line template, closing line then name' => ["`one\ntwo` | User", 'User', true],
        'multi-line template, name in its text' => ["`User\nline`", 'User', true],
        'multi-line template, placeholder on middle line' => ["`a\n\${User}\nb`", 'User', true],
        'multi-line template, quote on opening line' => ["`it's\nx` | User", 'User', true],
        'comment with apostrophe then name then quote' => ["/* it's */ User | 'x'", 'User', true],
        'name as JSON-quoted key' => ['{ "User": string }', 'User', true],
        'name after JSON-quoted key' => ['{ "a-b": User }', 'User', true],
    ]);

    test('typeNameOccursIn() counts a name inside or after a comment, an unpaired quote or an unclosed literal', function (string $haystack, bool $found) {
        expect($this->service->typeNameOccursIn('User', $haystack))->toBe($found);
    })->with([
        'a name inside a block comment' => ['/* User */ string', true],
        'a name inside a line comment' => ["string // User\n| null", true],
        'a quote inside a line comment' => ["// it's\nUser | 'x'", true],
        'a backtick inside a block comment' => ['/* ` */ User | `x`', true],
        'a block comment that never closes' => ["/* it's 'x' | User", true],
        'a quote whose partner is on the next line' => ["'a\nUser | b'", true],
        'an escaped newline inside a quoted string' => ["'a\\\nUser'", true],
        'an unpaired quote, then a backtick, then the name on the next line' => ["'it`s\nUser | `x`", true],
        'a quoted string inside a template literal that never closes' => ["`abc 'User'", true],
        'a quoted string inside a placeholder that never closes' => ["`a \${ 'User' `b", true],
        'a placeholder spanning lines' => ["`a\${\nUser\n}b`", true],
        'a line comment inside a placeholder' => ["`\${ // }\nUser }`", true],
        'a quote in a template literal spanning lines, the name in its text' => ["`it's\nUser` | 'x'", true],
    ]);

    // Re-review 3's drop-direction shapes: TypeScript reads each name, and a lexer that paired quotes, ended `//` comments
    // or bounded tokens differently hid it, so the prune dropped an import the file needed.
    test('typeNameOccursIn() counts a name after a line continuation, an odd line terminator or a spread', function (string $haystack, string $name) {
        expect($this->service->typeNameOccursIn($name, $haystack))->toBeTrue();
    })->with([
        'a line continuation, then quoted arms around the name' => ["'a\\\nb' | 'x' | User | 'y'", 'User'],
        'a line continuation in double quotes' => ["\"a\\\nb\" | \"x\" | User | \"y\"", 'User'],
        'a CRLF line continuation' => ["'a\\\r\nb' | 'x' | User | 'y'", 'User'],
        'a CR line continuation' => ["'a\\\rb' | 'x' | User | 'y'", 'User'],
        'a line continuation in an object key' => ["{ 'a\\\nb': 'x'; c: User; d: 'y' }", 'User'],
        'a line continuation in a placeholder string' => ["`\${'a\\\nb' | 'x}' | StatusType}`", 'StatusType'],
        'a line continuation, then the name alone' => ["'a\\\nb' | User", 'User'],
        'a line comment ended by CR' => ["// c\r`a\nb` | User | `x`", 'User'],
        'a line comment ended by U+2028' => ["// c\u{2028}`a\nb` | User | `x`", 'User'],
        'a line comment ended by U+2029' => ["// c\u{2029}`a\nb` | User | `x`", 'User'],
        'a line comment ended by LF' => ["// c\n`a\nb` | User | `x`", 'User'],
        'a line comment ended by CRLF' => ["// c\r\n`a\nb` | User | `x`", 'User'],
        'a variadic tuple element' => ['[string, ...User[]]', 'User'],
        'a variadic tuple head naming a #[TsType] class' => ['[...MenuSettingsType[], string]', 'MenuSettingsType'],
        'a variadic tuple element naming an enum' => ['[string, ...StatusType[]]', 'StatusType'],
        'a variadic tuple element after a quoted name' => ["'User' | [string, ...User[]]", 'User'],
    ]);

    // chr(92) keeps each `\u` escape a literal backslash, whatever decodes such escapes on the way into this file.
    test('typeNameOccursIn() decodes an identifier escape, as TypeScript does', function (string $haystack, string $name, bool $found) {
        expect($this->service->typeNameOccursIn($name, $haystack))->toBe($found);
    })->with([
        'a four-digit escape' => [chr(92).'u0055ser', 'User', true],
        'a braced escape' => [chr(92).'u{55}ser', 'User', true],
        'an escape inside the name' => ['Us'.chr(92).'u0065r', 'User', true],
        'two braced escapes with leading zeros' => [chr(92).'u{0055}'.chr(92).'u{0073}er', 'User', true],
        'an escaped enum name' => [chr(92).'u0053tatusType | null', 'StatusType', true],
        'an escaped #[TsType] name' => [chr(92).'u004DenuSettingsType', 'MenuSettingsType', true],
        'an escape after an escaped backslash' => [chr(92).chr(92).'u0055ser', 'User', true],
        'an escape inside a string literal' => ["'".chr(92)."u0055ser'", 'User', true],
        'an escape of a dot, which TypeScript reads as the name u002EUser' => [chr(92).'u002EUser', 'u002EUser', true],
        'an escape of a dot is not the name' => [chr(92).'u002EUser', 'User', false],
        'an escape past the last code point' => [chr(92).'u{110000}User', 'User', true],
        'a lone surrogate escape' => [chr(92).'uD800User', 'uD800User', true],
        'an escape that joins the name to a longer identifier' => [chr(92).'u0041User', 'User', false],
        'an escape that joins the name to a longer identifier, as TypeScript reads it' => [chr(92).'u0041User', 'AUser', true],
    ]);

    test('typeNameOccursIn() reads every type it is given, and none is no match', function () {
        $s = $this->service;
        expect($s->typeNameOccursIn('User', "`\${string}'s label", 'User | `x`'))->toBeTrue()
            ->and($s->typeNameOccursIn('User', "`\${string}'s label\nUser | `x`"))->toBeTrue()
            // Two #[TsCasts] values TypeScript lexes as one once emitted: the first opens a template the second closes.
            ->and($s->typeNameOccursIn('User', '`a', 'x` | User | `y`'))->toBeTrue()
            ->and($s->typeNameOccursIn('User', "`a\nx` | User | `y`"))->toBeTrue()
            ->and($s->typeNameOccursIn('User', 'string', 'number'))->toBeFalse()
            ->and($s->typeNameOccursIn('User'))->toBeFalse();
    });

    test('typeNameOccursIn() reads a deeply nested template literal in one pass', function () {
        $depth = 2000;
        $textOnly = str_repeat('`${', $depth).'string'.str_repeat('}User`', $depth);

        expect($this->service->typeNameOccursIn('User', str_repeat('`${', $depth).'User'.str_repeat('}`', $depth)))->toBeTrue()
            ->and($this->service->typeNameOccursIn('User', $textOnly))->toBeTrue()
            ->and($this->service->typeNameOccursIn('Post', $textOnly))->toBeFalse()
            ->and($this->service->typeNameOccursIn('Post', $textOnly.' | Post'))->toBeTrue();
    });
});

describe('isUnknownOnly', function () {
    test('only a type whose non-null arms are all unknown is unknown-only', function (string $type, bool $expected) {
        expect($this->service->isUnknownOnly($type))->toBe($expected);
    })->with([
        'bare unknown' => ['unknown', true],
        'unknown with a null arm' => ['unknown | null', true],
        'null before unknown' => ['null | unknown', true],
        'a real type' => ['string', false],
        'a real type with a null arm' => ['User | null', false],
        'unknown beside a real arm' => ['unknown | string', false],
        'an unknown inside a shape' => ['{ a: unknown }', false],
        'an unknown element type' => ['unknown[]', false],
    ]);
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
