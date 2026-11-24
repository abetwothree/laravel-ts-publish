<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DeclaredArms;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\ReturnShape\DeclaredFill;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Cache\CacheBootstrap;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Runners\BaseRunner;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\AnnulledResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ChainAccessorReturnResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\NullableShorthandWrappedResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\VariantGapReturnResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\CastNullWarehouse;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ColumnAccessorPost;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\CategoryResource;
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Http\Resources\DocblockReads\DeclaredArmsResource;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Category;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\Support\Opaque;
use Workbench\App\ValueObjects\DocblockReads\HasLabel;
use Workbench\App\ValueObjects\DocblockReads\MaybeLabel;

/**
 * The type a closure returning one value resolves to through a resource, PostResource unless given, the value read
 * bare or, given a tag, as a local that tag declares inline, after any statements the prelude holds.
 *
 * @param  class-string  $resource
 * @param  class-string  $model
 */
function declaredArmsType(
    string $value,
    string $parameters = '',
    ?string $tag = null,
    string $prelude = '',
    string $resource = PostResource::class,
    string $model = Post::class,
): string {
    $body = $prelude.($tag === null ? 'return '.$value.';' : '/** @var '.$tag.' $z */ $z = '.$value.'; return $z;');
    $expr = new AstParser()->parseSource('<?php function ('.$parameters.') { '.$body.' };')[0]->expr;

    return new ResourceAstAnalyzer(new ReflectionClass($resource), $model)->resolve($expr)['type'];
}

/**
 * The whole result a value reads as through PostResource, as a local the tag declares inline.
 *
 * @return array<string, mixed>
 */
function declaredArmsResult(string $value, string $tag): array
{
    $body = '/** @var '.$tag.' $z */ $z = '.$value.'; return $z;';
    $expr = new AstParser()->parseSource('<?php function () { '.$body.' };')[0]->expr;

    return new ResourceAstAnalyzer(new ReflectionClass(PostResource::class), Post::class)->resolve($expr);
}

/**
 * Whether a text spells either mark.
 */
function spellsDeclaredMark(string $text): bool
{
    return str_contains($text, VisibleGaps::DECLARED_NULL) || str_contains($text, VisibleGaps::DECLARED_KEYS);
}

describe('the marks', function () {
    test('a mark is a gap a reader carries, and stripping turns it back into what it stands for', function () {
        expect(VisibleGaps::isGap(VisibleGaps::DECLARED_NULL))->toBeTrue()
            ->and(VisibleGaps::isGap(VisibleGaps::DECLARED_KEYS))->toBeTrue()
            ->and(VisibleGaps::isMark(VisibleGaps::DECLARED_NULL))->toBeTrue()
            ->and(VisibleGaps::isMark('$gap1'))->toBeFalse()
            ->and(VisibleGaps::holds('string | $declNull'))->toBeTrue()
            ->and(VisibleGaps::holdsUntyped('string | $declNull'))->toBeFalse()
            ->and(VisibleGaps::stripped('(string | $declNull)[] | $gap2'))->toBe(['(string | null)[]', [2]])
            ->and(VisibleGaps::stripped('string | null | $declNull'))->toBe(['string | null', []])
            ->and(VisibleGaps::stripped('string[] | Record<string, string> | $declKeys'))->toBe(['string[] | Record<string, string>', []])
            ->and(VisibleGaps::stripped('(string | $declNull)[] | $gap2 | $declKeys', keepsMarks: true))->toBe(['(string | $declNull)[] | $declKeys', [2]])
            ->and(VisibleGaps::ids('string | $declNull'))->toBe([])
            ->and(VisibleGaps::unmarked('{ a: string | $declNull }[] | $declKeys'))->toBe('{ a: string | null }[]');
    });

    test('a result is unmarked in its type and in the one object a split literal prints', function () {
        $split = [
            'type' => '{ k: \'a\'; v: number } | { k: \'b\'; v: string }',
            'optional' => false,
            'unsplit' => [
                'of' => '{ k: \'a\'; v: number } | { k: \'b\'; v: string }',
                'print' => ['type' => '{ k: \'a\' | \'b\'; v: number | string | $declNull }', 'optional' => false],
            ],
        ];

        expect(DeclaredArms::unmarked($split)['unsplit']['print']['type'])->toBe("{ k: 'a' | 'b'; v: number | string | null }")
            ->and(DeclaredArms::unmarked(['type' => 'string | $declNull', 'optional' => false])['type'])->toBe('string | null');
    });

    test('marks a type\'s own null only beside another arm', function (string $type, string $expected) {
        expect(VisibleGaps::nullMarked($type))->toBe($expected);
    })->with([
        'a nullable type' => ['string | null', 'string | $declNull'],
        'a nested null' => ['(string | null)[]', '(string | null)[]'],
        'a lone null' => ['null', 'null'],
        'no null' => ['string', 'string'],
    ]);

    test('a reader reads a declared null as null, and a carried read marks again the null it keeps', function () {
        expect(VisibleGaps::aside('string | $declNull | $gap1'))->toBe(['string | null', ['$gap1']])
            ->and(VisibleGaps::aside('string | $declNull', marks: true))->toBe(['string', ['$declNull']])
            ->and(VisibleGaps::aside('string | null | $declNull'))->toBe(['string | null', []])
            ->and(VisibleGaps::carried('string[] | $declNull', TsTypeShape::elementType(...)))->toBe('string')
            ->and(VisibleGaps::carried('string | $declNull', static fn (string $type): string => 'Array<'.$type.'>'))->toBe('Array<string | null>')
            ->and(VisibleGaps::carried('string | $declNull', static fn (string $type): string => $type))->toBe('string | $declNull')
            ->and(VisibleGaps::carried('string | null | $declNull', static fn (string $type): string => $type))->toBe('string | null');
    });

    test('the null helpers read a declared null as the null it stands for', function () {
        expect(TsTypeString::hasNullArm('string | $declNull'))->toBeTrue()
            ->and(TsTypeString::withoutNullArm('string | $declNull'))->toBe('string')
            ->and(TsTypeString::withNullArmOf('string | $declNull', 'number'))->toBe('number | $declNull')
            ->and(TsTypeString::withNullArmOf('string | null | $declNull', 'number'))->toBe('number | null')
            ->and(TsTypeString::hoistNull(['string | $declNull', 'number']))->toBe('string | number | $declNull')
            ->and(TsTypeString::hoistNull(['string | $declNull', 'null']))->toBe('string | null')
            ->and(TsTypeString::isVagueOnly('unknown[] | $declNull'))->toBeTrue()
            ->and(TsTypeString::isUnknownOnly('unknown | $declNull'))->toBeTrue();
    });
});

describe('the declaration mode', function () {
    test('every source reads as it always has out of the mode', function () {
        expect(DeclaredArms::markedNull('string | null'))->toBe('string | null')
            ->and(DeclaredArms::attribute('string | null', Post::class, 'featured_image_url'))->toBe('string | null')
            ->and(DeclaredArms::keysHedged('string[] | Record<string, string>'))->toBe('string[] | Record<string, string>')
            ->and(declaredArmsType('$this->featured_image_url'))->toBe('string | null');
    });

    test('a source marks only the null a declaration produced, in the mode', function () {
        $marked = resolve(AnalysisMemo::class)->markingDeclared(static fn (): array => [
            DeclaredArms::markedNull('string | null'),
            DeclaredArms::markedNull('null'),
            DeclaredArms::attribute('string | null', Post::class, 'featured_image_url'),
            DeclaredArms::attribute('string | null', Image::class, 'aspect_ratio'),
            DeclaredArms::keysHedged('string[] | Record<string, string>'),
        ]);

        expect($marked)->toBe([
            'string | $declNull',
            'null',
            'string | $declNull',
            'string | null',
            'string[] | Record<string, string> | $declKeys',
        ]);
    });
});

// Each kind's null comes back at the reading; under an outer `@var` that omits it, it goes.
test('an outer @var replaces each kind of arm only a declaration produced', function (string $value, string $parameters, string $tag, string $bare, string $declared) {
    expect(declaredArmsType($value, $parameters))->toBe($bare)
        ->and(declaredArmsType($value, $parameters, $tag))->toBe($declared);
})->with([
    'a nullable column' => ['$this->featured_image_url', '', 'string', 'string | null', 'string'],
    'a nullable cast column' => ['$this->published_at', '', 'string', 'string | null', 'string'],
    'a nullable relation' => ['$c->parent', '\\'.Category::class.' $c', '\\'.Category::class, 'Category | null', 'Category'],
    'a ?T method return' => ['$l->label()', '\\'.MaybeLabel::class.' $l', 'string', 'string | null', 'string'],
    'a closure\'s native ?T over a body it cannot read' => [
        'collect([1])->map(fn ($i): ?\\'.Post::class.' => \\'.Opaque::class.'::value($i))->all()',
        '',
        'list<\\'.Post::class.'>',
        '(Post | null)[]',
        'Post[]',
    ],
    'a ?X parameter' => [
        'array_map(fn (?\\'.Post::class.' $p) => $p, \\'.Opaque::class.'::value([]))',
        '',
        'list<\\'.Post::class.'>',
        '(Post | null)[] | Record<string, Post | null>',
        'Post[]',
    ],
    'a parameter defaulting to null' => [
        'array_map(fn (?string $s = null) => $s, \\'.Opaque::class.'::value([]))',
        '',
        'list<string>',
        '(string | null)[] | Record<string, string | null>',
        'string[]',
    ],
    'an @param X|null' => [
        'collect([1])->map(/** @param \\'.HasLabel::class.'|null $l */ fn ($l) => $l?->label())->values()->all()',
        '',
        'list<string>',
        '(string | null)[]',
        'string[]',
    ],
    '?-> over a receiver holding no proven null' => [
        'collect([$this->resource])->map(fn (?\\'.Post::class.' $p) => $p?->title)->all()',
        '',
        'list<string>',
        '(string | null)[]',
        'string[]',
    ],
    'find() on a query' => ['\\'.Post::class.'::find(1)', '', '\\'.Post::class, 'Post | null', 'Post'],
    'first() on a query' => ['\\'.Post::class.'::query()->first()', '', '\\'.Post::class, 'Post | null', 'Post'],
    'firstWhere() on a query' => ['\\'.Post::class.'::query()->firstWhere(\'id\', 1)', '', '\\'.Post::class, 'Post | null', 'Post'],
    'first() on a relation\'s models' => ['$this->resource->comments->first()', '', '\\'.Comment::class, 'Comment | null', 'Comment'],
    'auth()->user()' => ['auth()->user()', '', '\\'.User::class, 'User | null', 'User'],
    'auth()->id()' => ['auth()->id()', '', 'int', 'number | null', 'number'],
    'value() on a query' => ['\\'.Post::class.'::query()->value(\'title\')', '', 'string', 'string | null', 'string'],
    '?-> over tryFrom()' => ['\\'.Status::class.'::tryFrom($v)?->value', 'int $v', 'int', 'StatusType | null', 'StatusType'],
    'a function\'s own nullable parameter' => ['$p', '?\\'.Post::class.' $p', '\\'.Post::class, 'Post | null', 'Post'],
    'a function\'s own parameter defaulting to null' => ['$s', '?string $s = null', 'string', 'string | null', 'string'],
    '?-> over a function\'s own nullable parameter' => ['$p?->title', '?\\'.Post::class.' $p', 'string', 'string | null', 'string'],
    'an untyped array\'s Record twin' => [
        'array_map(fn (\\'.Post::class.' $p) => $p->title, \\'.Opaque::class.'::value([]))',
        '',
        'list<string>',
        'string[] | Record<string, string>',
        'string[]',
    ],
]);

test('an outer @var replaces the null first() declares and a relation\'s, read whole or through whenLoaded()', function () {
    $comments = '/** @var \\'.Collection::class.'<int, \\'.Comment::class.'> $comments */ $comments = \\'.Opaque::class.'::value([]); ';
    $category = static fn (string $value, ?string $tag): string => declaredArmsType(
        $value,
        tag: $tag,
        resource: CategoryResource::class,
        model: Category::class,
    );

    expect(declaredArmsType('$comments->first()?->content', prelude: $comments))->toBe('string | null')
        ->and(declaredArmsType('$comments->first()?->content', tag: 'string', prelude: $comments))->toBe('string')
        ->and($category('$this->whenLoaded(\'parent\')', null))->toBe('Category | null')
        ->and($category('$this->whenLoaded(\'parent\')', '\\'.Category::class))->toBe('Category')
        ->and($category('$this->parent', null))->toBe('Category | null')
        ->and($category('$this->parent', '\\'.Category::class))->toBe('Category');
});

test('an outer @var replaces the null a docblock declares on $resource, read whole or through ?->', function () {
    $proxy = static fn (string $value, ?string $tag): string => declaredArmsType(
        $value,
        tag: $tag,
        resource: NullableShorthandWrappedResource::class,
    );

    expect($proxy('$this->resource', null))->toBe('Post | null')
        ->and($proxy('$this->resource', '\\'.Post::class))->toBe('Post')
        ->and($proxy('$this->resource?->title', null))->toBe('string | null')
        ->and($proxy('$this->resource?->title', 'string'))->toBe('string');
});

// A resource class or an `EnumResource` wrap is no type a tag reads, so these are pinned where they mark.
test('marks the null a resource\'s ?static method and a tryFrom() wrap declare, in the mode', function () {
    $marked = static fn (string $value, string $parameters = ''): string => resolve(AnalysisMemo::class)
        ->markingDeclared(static fn (): string => declaredArmsType($value, $parameters));
    $maybe = '\\'.AnnulledResource::class.'::make($this->resource)->maybe()';
    $wrap = '\\'.EnumResource::class.'::make(\\'.Status::class.'::tryFrom($v))';

    expect(declaredArmsType($maybe))->toBe('AnnulledResource | null')
        ->and($marked($maybe))->toBe('AnnulledResource | $declNull')
        ->and(declaredArmsType($wrap, 'int $v'))->toBe('AsEnum<typeof Status> | null')
        ->and($marked($wrap, 'int $v'))->toBe('AsEnum<typeof Status> | $declNull');
});

test('an outer @var keeps each arm the body proves, and a null no declaration types', function (string $value, string $parameters, string $tag, string $expected) {
    expect(declaredArmsType($value, $parameters, $tag))->toBe($expected);
})->with([
    'a ternary\'s literal null' => ['$this->id > 1 ? $this->title : null', '', 'string', 'string | null'],
    '?? null' => ['$this->featured_image_url ?? null', '', 'string', 'string | null'],
    '?-> over a receiver holding a proven null' => ['($this->id > 1 ? $this->resource : null)?->title', '', 'string', 'string | null'],
    'an accessor whose body returns null' => ['$i->aspect_ratio', '\\'.Image::class.' $i', 'string', 'string | null'],
    'an accessor whose body reads a nullable column' => ['$p->image', '\\'.ColumnAccessorPost::class.' $p', 'string', 'string | null'],
    'a declaration that keeps the null' => ['$this->featured_image_url', '', 'string|null', 'string | null'],
    'a declaration that types nothing' => ['$this->featured_image_url', '', 'mixed', 'string | null'],
    'a key the declaration lacks' => ['[\'a\' => 1, \'b\' => $this->featured_image_url]', '', 'array{a: int}', '{ a: number; b: string | null }'],
    'a declared null beside a proven one' => ['$this->id > 1 ? $this->featured_image_url : null', '', 'string', 'string | null'],
]);

// A source that adds a declared `null` beside a value keeps every `null` the value itself proves.
test('an outer @var keeps a null the value proves where a declared one joins it', function (string $value, string $resource, string $model) {
    expect(declaredArmsType($value, resource: $resource, model: $model))->toBe('string | null')
        ->and(declaredArmsType($value, tag: 'string', resource: $resource, model: $model))->toBe('string | null');
})->with([
    '?-> in a $this chain over an accessor' => ['$this->post?->excerpt', CommentResource::class, Comment::class],
    '?-> over a nullable $resource\'s accessor' => ['$this->resource?->excerpt', NullableShorthandWrappedResource::class, Post::class],
    'whenLoaded() of a declared-nullable relation over a ternary null' => [
        '$this->whenLoaded(\'parent\', fn () => $this->id > 1 ? $this->name : null)',
        CategoryResource::class,
        Category::class,
    ],
    'whenLoaded() of a declared-nullable relation over ?? null' => [
        '$this->whenLoaded(\'parent\', fn () => $this->parent->description ?? null)',
        CategoryResource::class,
        Category::class,
    ],
    'value() of an accessor' => ['\\'.Post::class.'::query()->value(\'title_display\')', PostResource::class, Post::class],
    'first() of a plucked accessor' => ['\\'.Post::class.'::query()->pluck(\'excerpt\')->first()', PostResource::class, Post::class],
]);

test('an outer @var replaces a declared null joining a value that proves none', function (string $value, string $resource, string $model) {
    expect(declaredArmsType($value, resource: $resource, model: $model))->toBe('string | null')
        ->and(declaredArmsType($value, tag: 'string', resource: $resource, model: $model))->toBe('string');
})->with([
    '?-> in a $this chain over a nullable column' => ['$this->post?->featured_image_url', CommentResource::class, Comment::class],
    'whenLoaded() of a declared-nullable relation over a NOT NULL column' => [
        '$this->whenLoaded(\'parent\', fn () => $this->parent->name)',
        CategoryResource::class,
        Category::class,
    ],
]);

test('an outer @var replaces the null of a nullable column a cast class reads', function (string $column, string $tag, string $type) {
    expect(declaredArmsType('$this->'.$column, model: CastNullWarehouse::class))->toBe($type.' | null')
        ->and(declaredArmsType('$this->'.$column, tag: $tag, model: CastNullWarehouse::class))->toBe($type);
})->with([
    'a get() that passes the stored value through' => ['coordinate_data', 'string', 'string'],
    'a castable whose get() never returns null' => ['status', 'array{label: string}', '{ label: string }'],
]);

// A helper's body is read under the `@var` too, so its frame keeps each mark for that declaration to replace.
test('an outer declaration over another method\'s body keeps the null it proves and replaces a declared one', function () {
    expect(publishedResourceKeyTypes(ChainAccessorReturnResource::class, Comment::class))->toBe([
        'chain_accessor' => 'string | null',
        'closure_accessor' => '(string | null)[]',
        'bare_chain_accessor' => 'string | null',
        'var_column_helper' => '{ parent: number }',
        'var_ternary_helper' => '{ parent: number | null }',
        'bare_column_helper' => '{ parent: number | null }',
    ]);
});

test('a declaration laid whole over a reading keeps what the reading says the value is', function () {
    $reading = ['type' => 'string | $declNull', 'optional' => true, 'absent' => true, 'removed' => true, 'keepsKeys' => true, 'listKeys' => true];
    $cast = '(object) [\'a\' => $this->featured_image_url]';

    expect(DeclaredFill::merged($reading, ['type' => 'string', 'optional' => false]))
        ->toBe(['type' => 'string', 'optional' => true, 'absent' => true, 'removed' => true, 'listKeys' => true, 'keepsKeys' => true])
        ->and(declaredArmsResult($cast, 'array{a: string}'))->toMatchArray(['type' => '{ a: string }', 'keepsKeys' => true]);
});

// The replay strips no gap: a gap a variant's key holds stays for the `@return` that fills it, and warns no earlier.
test('a variant literal keeps a gap for the declaration of the key it reaches', function () {
    AnalysisWarnings::reset();
    resolve(AnalysisMemo::class)->reset();

    expect(publishedResourceKeyTypes(VariantGapReturnResource::class, Post::class))->toBe([
        'nested' => '{ pinned: true; v: number; other: number | null } | { pinned: false; v: string; other: number | null }',
    ])->and(AnalysisWarnings::all())->toBe([]);
});

test('a declaration that names a subclass of the class a reading holds refines it', function () {
    $model = 'collect([1])->map(fn ($i): \\Illuminate\\Database\\Eloquent\\Model => \\'.Opaque::class.'::value($i))->all()';

    expect(declaredArmsType($model))->toBe('Model[]')
        ->and(declaredArmsType($model, '', 'list<\\'.Post::class.'>'))->toBe('Post[]');
});

test('the workbench fixture reads each outer declaration over each kind of arm', function () {
    expect(publishedResourceKeyTypes(DeclaredArmsResource::class, Post::class))->toBe([
        'var_closure_nullable' => 'Post[]',
        'var_closure_model' => 'Post[]',
        'var_closure_model_narrowed' => 'Post[]',
        'var_closure_filtered' => 'Post[]',
        'var_two_array_nullsafe' => 'string[]',
        'var_param_nullsafe' => 'string[]',
        'var_nullable_return' => 'string[]',
        'var_hedged_map' => 'string[]',
        'var_first_kid_name' => 'string',
        'var_parent_ids' => 'number[]',
        'var_column' => 'string',
        'var_mapped_column' => 'string[]',
        'var_zipped' => '{ content: string; other: number }[]',
        'var_flags' => 'true[]',
        'decl_closure_nullable' => 'Post[]',
        'decl_closure_array' => '{ a: number }[]',
        'decl_two_array_nullsafe' => 'string[]',
        'decl_param_nullsafe' => 'string[]',
        'decl_nullable_return' => 'string[]',
        'decl_hedged_map' => 'string[]',
        'decl_first_kid_name' => 'string',
        'decl_column' => 'string',
        'when_closure_nullable?' => 'Post[]',
        'when_closure_array?' => '{ a: number }[]',
        'when_column?' => 'string',
        'when_first_kid_name?' => 'string',
        'when_flags?' => 'true[]',
        'bare_closure_nullable' => '(Post | null)[]',
        'bare_param_nullsafe' => '(string | null)[]',
        'bare_hedged_map' => '(string | null)[] | Record<string, string | null>',
        'bare_first_kid_name' => 'string | null',
        'bare_column' => 'string | null',
        'bare_zipped' => '{ content: string; other: unknown }[]',
        'var_ternary' => 'string | null',
        'var_coalesce_null' => 'string | null',
        'var_coalesced' => 'string',
        'var_uncovered' => '{ a: number; b: string | null }',
        'var_mixed' => 'string | null',
        'var_nullable_column' => 'string | null',
        'var_proven_nullsafe' => 'string | null',
        'var_mapped_coalesce_null' => '(string | null)[]',
        'decl_ternary' => 'string | null',
        'decl_coalesce_null' => 'string | null',
        'decl_nullable_column' => 'string | null',
        'when_ternary?' => 'string | null',
        'when_nullable_column?' => 'string | null',
    ]);
});

describe('the leak guard', function () {
    test('no published workbench file spells a mark', function () {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            dirname(__DIR__, 3).'/workbench/resources/js/types',
            FilesystemIterator::SKIP_DOTS,
        ));
        $leaks = [];

        foreach ($files as $file) {
            if ($file->isFile() && spellsDeclaredMark((string) file_get_contents($file->getPathname()))) {
                $leaks[] = $file->getPathname();
            }
        }

        expect(iterator_count($files))->toBeGreaterThan(100)
            ->and($leaks)->toBe([]);
    });

    test('no analysis, memo entry read out of the modes or cached transformer spells a mark', function () {
        $memo = resolve(AnalysisMemo::class);
        $memo->reset();
        $analysis = [
            resolve(AstEngine::class)->analyze(DeclaredArmsResource::class, 'toArray', Post::class),
            resolve(AstEngine::class)->analyze(ChainAccessorReturnResource::class, 'toArray', Comment::class),
        ];
        /** @var array<string, list<array{value: mixed}>> $entries */
        $entries = (fn (): array => $this->entries)->call($memo);
        $outOfModes = array_filter(
            $entries,
            static fn (string $key): bool => ! str_ends_with($key, '@gaps') && ! str_ends_with($key, '@declared'),
            ARRAY_FILTER_USE_KEY,
        );
        $leaked = array_keys(array_filter($outOfModes, static fn (array $stored): bool => spellsDeclaredMark(serialize($stored))));

        $directory = sys_get_temp_dir().'/ts-publish-marks-'.uniqid();
        Config::set('ts-publish.cache.store', null);
        Config::set('ts-publish.cache.directory', $directory);
        $runner = new class extends BaseRunner
        {
            public function run(): void {}

            public function build(string $fqcn): void
            {
                $this->cachedGenerate($fqcn, ResourceGenerator::class);
            }
        };

        try {
            $manifest = CacheBootstrap::manifest();
            $runner->useCache($manifest);
            $runner->build(DeclaredArmsResource::class);
            $snapshot = base64_decode((string) $manifest->snapshot(ResourceGenerator::class.'::'.DeclaredArmsResource::class));
        } finally {
            File::deleteDirectory($directory);
        }

        expect(spellsDeclaredMark(serialize($analysis)))->toBeFalse()
            ->and(count($entries))->toBeGreaterThan(count($outOfModes))
            ->and($leaked)->toBe([])
            ->and($snapshot)->toContain('var_closure_nullable')
            ->and(spellsDeclaredMark($snapshot))->toBeFalse();
    });
});
