<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\LaravelTsPublish as LaravelTsPublishService;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Workbench\App\Models\Activity;
use Workbench\App\Models\Admin\Store;
use Workbench\App\Models\ArrayObjectCastFixture;
use Workbench\App\Models\Attachment;
use Workbench\App\Models\CompositeComment;
use Workbench\App\Models\Image;
use Workbench\App\Models\Kpi;
use Workbench\App\Models\Marketing\Report\Report as MarketingReport;
use Workbench\App\Models\Order;
use Workbench\App\Models\OrderItem;
use Workbench\App\Models\Post;
use Workbench\App\Models\Product;
use Workbench\App\Models\Profile;
use Workbench\App\Models\PropertyDocblockBase;
use Workbench\App\Models\PropertyDocblockChild;
use Workbench\App\Models\PropertyDocblockDescribedTagFixture;
use Workbench\App\Models\PropertyDocblockEdge;
use Workbench\App\Models\PropertyDocblockRejectFixture;
use Workbench\App\Models\PropertyDocblockTraitFixture;
use Workbench\App\Models\Sales\Report\Report as SalesReport;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;

test('resolveAttribute returns empty info for non-existent model class', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    $result = $resolver->resolveAttribute('App\\Models\\NonExistent', 'name');

    expect($result)->toBe(LaravelTsPublish::emptyTypeScriptInfo());
});

test('resolveAttribute returns unknown for a write-only mutator with no getter', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    // 'search_index' is a write-only mutator (no getter, no docblock Get generic), not a DB column
    $result = $resolver->resolveAttribute(Order::class, 'search_index');

    expect($result['type'])->toBe('unknown');
});

test('resolveRelation returns unknown for non-existent model class', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    $result = $resolver->resolveRelation('App\\Models\\NonExistent', 'posts');

    expect($result)->toBe(['type' => 'unknown', 'modelFqcn' => null, 'morphFqcns' => []]);
});

test('resolveMethodReturnType returns empty info for non-existent method', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    $result = $resolver->resolveMethodReturnType(User::class, 'nonExistentMethod');

    expect($result)->toBe(LaravelTsPublish::emptyTypeScriptInfo());
});

test('resolveMethodReturnType returns empty info for non-existent class', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    $result = $resolver->resolveMethodReturnType('App\\Models\\NonExistent', 'nonExistentMethod');

    expect($result)->toBe(LaravelTsPublish::emptyTypeScriptInfo());
});

test('resolveAccessorModelFqcn returns null for non-existent model class', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    $result = $resolver->resolveAccessorModelFqcn('App\\Models\\NonExistent', 'name');

    expect($result)->toBeNull();
});

test('resolveAccessorModelFqcn returns null for non-accessor attribute', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    $result = $resolver->resolveAccessorModelFqcn(User::class, 'name');

    expect($result)->toBeNull();
});

test('resolveAccessorModelFqcn returns null when accessor does not return a Model', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    $result = $resolver->resolveAccessorModelFqcn(User::class, 'initials');

    expect($result)->toBeNull();
});

test('getAttributes returns null for non-existent model class', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    expect($resolver->getAttributes('App\\Models\\NonExistent'))->toBeNull();
});

test('getRelations returns null for non-existent model class', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    expect($resolver->getRelations('App\\Models\\NonExistent'))->toBeNull();
});

test('getRelationNullable returns null for non-existent model class', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    expect($resolver->getRelationNullable('App\\Models\\NonExistent'))->toBeNull();
});

test('getInstance returns null for non-existent model class', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    expect($resolver->getInstance('App\\Models\\NonExistent'))->toBeNull();
});

test('getReflection returns null for non-existent model class', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    expect($resolver->getReflection('App\\Models\\NonExistent'))->toBeNull();
});

test('publishedColumnNames tracks the exclude_hidden setting', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    config()->set('ts-publish.models.exclude_hidden', false);
    expect($resolver->publishedColumnNames(User::class))->toContain('password');

    config()->set('ts-publish.models.exclude_hidden', true);
    expect($resolver->publishedColumnNames(User::class))->not->toContain('password');
});

test('buildMorphTargetMap builds map from MorphMany inverse relations', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    $resolver->buildMorphTargetMap([
        User::class,
        Post::class,
        Product::class,
        Image::class,
    ]);

    // User, Post, and Product all have morphMany(Image::class, 'imageable')
    $targets = $resolver->getMorphToTargets(Image::class, 'imageable');

    expect($targets)->toBe([Post::class, Product::class, User::class]);
});

test('getMorphToTargets returns empty array when no inverse relations exist', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    $resolver->buildMorphTargetMap([
        User::class,
        Post::class,
        Image::class,
    ]);

    expect($resolver->getMorphToTargets(CompositeComment::class, 'commentable'))->toBe([]);
});

test('getMorphToTargets returns empty array when map is not built', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    expect($resolver->getMorphToTargets(Image::class, 'imageable'))->toBe([]);
});

test('buildMorphTargetMap skips non-existent model classes', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    $resolver->buildMorphTargetMap([
        'App\\Models\\NonExistent',
        User::class,
        Image::class,
    ]);

    $targets = $resolver->getMorphToTargets(Image::class, 'imageable');

    expect($targets)->toBe([User::class]);
});

test('getMorphToTargets falls back to the legacy childFqcn bucket for an unmatched morph name', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    $resolver->buildMorphTargetMap([
        User::class,
        Post::class,
        Product::class,
        Image::class,
    ]);

    // 'imageable' is Image's real morph name; a name no relation was ever keyed under still
    // resolves — via the plain childFqcn bucket — to the same aggregate, proving the keyed
    // lookup degrades gracefully rather than losing the union entirely.
    expect($resolver->getMorphToTargets(Image::class, 'not-a-real-morph-name'))
        ->toBe($resolver->getMorphToTargets(Image::class, 'imageable'))
        ->toBe([Post::class, Product::class, User::class]);
});

test('resolveRelation returns union type for MorphTo when targets exist', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    $resolver->buildMorphTargetMap([
        User::class,
        Post::class,
        Product::class,
        Image::class,
    ]);

    $result = $resolver->resolveRelation(Image::class, 'imageable');

    expect($result['type'])->toBe('Post | Product | User')
        ->and($result['modelFqcn'])->toBeNull()
        ->and($result['morphFqcns'])->toBe([Post::class, Product::class, User::class]);
});

test('resolveRelation returns unknown for MorphTo when no targets exist', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    $resolver->buildMorphTargetMap([Image::class]);

    $result = $resolver->resolveRelation(CompositeComment::class, 'commentable');

    // CompositeComment has nullable FK columns, but 'unknown' already admits null, so no
    // ' | null' suffix is appended.
    expect($result['type'])->toBe('unknown')
        ->and($result['modelFqcn'])->toBeNull();
});

test('morph target map includes parents declaring custom MorphOne subclasses', function () {
    $resolver = resolve(ModelAttributeResolver::class);
    $resolver->buildMorphTargetMap([Post::class, Attachment::class]);

    $info = $resolver->resolveRelation(Attachment::class, 'attachable');

    expect($info['type'])->toContain('Post');
});

test('a bare @return MorphTo<Model, $this> generic is not narrowing and falls through to the reverse map', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    // Kpi::reportable() carries `@return MorphTo<Model, $this>` — the useless, non-narrowing
    // kind — so it must still resolve via the reverse scan of Sales/Marketing Report's
    // morphMany('reportable'), not degrade to 'unknown' and not emit a literal 'Model' token.
    $resolver->buildMorphTargetMap([Kpi::class, SalesReport::class, MarketingReport::class]);

    $result = $resolver->resolveRelation(Kpi::class, 'reportable');

    expect($result['morphFqcns'])->toBe([MarketingReport::class, SalesReport::class])
        ->and($result['type'])->not->toContain('Model')
        ->and($result['type'])->not->toBe('unknown');
});

describe('morphTo docblock generics', function () {
    test('a concrete generic types the relation without any reverse relation', function () {
        // causer() is declared on the HasRelatableLinkedRecord trait (mirroring eagle's own
        // shape) with no reverse morphMany anywhere pointing at Activity — only the docblock
        // generic can type it.
        $info = resolve(ModelAttributeResolver::class)->resolveRelation(Activity::class, 'causer');

        expect($info['type'])->toContain('User')
            ->and($info['morphFqcns'])->toBe([User::class]);
    });

    test('a sibling morphTo\'s docblock generic does not pollute this one, and the unresolved one stays bare unknown', function () {
        $resolver = resolve(ModelAttributeResolver::class);

        // Resolve causer (concrete User generic) first: a per-model cache bug, rather than a
        // correct per-relation one, would leak its target into subject's bare-generic resolution.
        $resolver->resolveRelation(Activity::class, 'causer');

        // subject also has nullable FK columns, so a naive nullable append would read 'unknown | null'
        // — but 'unknown' already admits null, making that union redundant.
        $info = $resolver->resolveRelation(Activity::class, 'subject');

        expect($info['type'])->toBe('unknown');
    });

    test('two morphTos on one model do not share a target union', function () {
        $resolver = resolve(ModelAttributeResolver::class);
        $resolver->buildMorphTargetMap([Activity::class, Kpi::class, SalesReport::class, MarketingReport::class]);

        $causer = $resolver->resolveRelation(Activity::class, 'causer');
        $subject = $resolver->resolveRelation(Activity::class, 'subject');

        expect($causer['type'])->not->toBe($subject['type']);
    });
});

test('attributeDocblockReturnTypes captures nested generic getter type', function () {
    $method = new ReflectionMethod(Order::class, 'sortedItems');
    $info = app(LaravelTsPublishService::class)->attributeDocblockReturnTypes($method);

    expect($info['type'])->toBe('OrderItem[]')
        ->and($info['classFqcns'])->toBe([OrderItem::class]);
});

test('accessor with vague closure type is refined by Attribute docblock generics', function () {
    $info = resolve(ModelAttributeResolver::class)
        ->resolveAttribute(Order::class, 'sorted_items');

    expect($info['type'])->toBe('OrderItem[]');
});

test('Collection<int, X> narrows to an array, matching array<int, X>', function () {
    $info = resolve(ModelAttributeResolver::class)->resolveAttribute(Order::class, 'sorted_items');

    expect($info['type'])->toBe('OrderItem[]');
});

test('Collection<string, X> resolves to a keyed record', function () {
    $info = resolve(ModelAttributeResolver::class)->resolveAttribute(Order::class, 'keyed_items');

    expect($info['type'])->toBe('Record<string, OrderItem>');
});

test('trait-declared accessor generics resolve through the trait file imports, not the model file', function () {
    // summaryItems() is declared on the HasSummaries trait, which imports Store; Order itself
    // never imports Store and lives in a different namespace, so only the trait file's use-map can resolve it.
    $info = resolve(ModelAttributeResolver::class)
        ->resolveAttribute(Order::class, 'summary_items');

    expect($info['type'])->toBe('Store[]')
        ->and($info['classFqcns'])->toBe([Store::class]);
});

test('accessor with @phpstan-return docblock resolves through docblock', function () {
    $info = resolve(ModelAttributeResolver::class)
        ->resolveAttribute(Order::class, 'score_map');

    expect($info['type'])->toBe('Record<string, number>');
});

test('bare @return Attribute docblock does not override a usable closure signature type', function () {
    // 'unsortedItems' pairs a bare `@return Attribute` with a vague `: Collection` closure signature;
    // the @return parser must not resolve the bare word to Eloquent's own Attribute class.
    $info = resolve(ModelAttributeResolver::class)
        ->resolveAttribute(Order::class, 'unsorted_items');

    expect($info['type'])->not->toBe('Attribute')
        ->and($info['type'])->toBe('unknown[] | Record<string, unknown>')
        ->and($info['classFqcns'])->toBe([]);
});

test('attributeDocblockReturnTypes resolves Attribute<> written as a fully-qualified class name', function () {
    // A `.php.stub` fixture because Pint's fully_qualified_strict_types fixer would rewrite the
    // literal FQCN in the docblock down to a short auto-imported name; its finder only sees `*.php`.
    require_once __DIR__.'/../Fixtures/FqcnAttributeDocblockFixture.php.stub';

    $method = new ReflectionMethod(FqcnAttributeDocblockFixture::class, 'sortedByFqcnDocblock');
    $info = app(LaravelTsPublishService::class)->attributeDocblockReturnTypes($method);

    expect($info['type'])->toBe('string[]');
});

describe('@property docblock refinement', function () {
    test('refines an array cast to a typed record using an existing column', function () {
        // Post's 'options' casts to plain 'array' and is refined only by the class-level @property tag.
        $info = resolve(ModelAttributeResolver::class)
            ->resolveAttribute(Post::class, 'options');

        expect($info['type'])->toBe('Record<string, string> | null');
    });

    test('columns without a @property tag are unaffected by the refinement', function () {
        // Post's 'metadata' carries no @property tag.
        $info = resolve(ModelAttributeResolver::class)
            ->resolveAttribute(Post::class, 'metadata');

        expect($info['type'])->toBe('unknown[] | null');
    });

    test('a child class @property tag wins over the parent class tag for the same column', function () {
        // Both fixtures tag 'tags', with different shapes.
        $childInfo = resolve(ModelAttributeResolver::class)
            ->resolveAttribute(PropertyDocblockChild::class, 'tags');

        $parentInfo = resolve(ModelAttributeResolver::class)
            ->resolveAttribute(PropertyDocblockBase::class, 'tags');

        expect($childInfo['type'])->toBe('string[] | null')
            ->and($parentInfo['type'])->toBe('Record<string, string> | null');
    });

    test('a @property-write tag is never used to type a readable property', function () {
        // 'related_users' carries only a @property-write tag.
        $info = resolve(ModelAttributeResolver::class)
            ->resolveAttribute(PropertyDocblockEdge::class, 'related_users');

        expect($info['type'])->toBe('unknown[] | null');
    });

    test('a shorter @property tag does not match a column name it merely prefixes', function () {
        // The fixture tags `$meta`; the column under test is the longer 'meta_info'.
        $info = resolve(ModelAttributeResolver::class)
            ->resolveAttribute(PropertyDocblockEdge::class, 'meta_info');

        expect($info['type'])->toBe('unknown[] | null');
    });

    test('a @property-read tag naming a Model class refines to an importable class token', function () {
        $info = resolve(ModelAttributeResolver::class)
            ->resolveAttribute(PropertyDocblockEdge::class, 'owner_snapshot');

        expect($info['type'])->toBe('User | null')
            ->and($info['classFqcns'])->toBe([User::class]);
    });

    test('an imported @phpstan-type alias in @property resolves to its shape, keeping optional keys', function () {
        // grid_config's @property tags a GridConfig alias imported from GridConfigDto via
        // @phpstan-import-type; the alias must expand inline rather than degrade to unknown[].
        $info = resolve(ModelAttributeResolver::class)
            ->resolveAttribute(Team::class, 'grid_config');

        expect($info['type'])
            ->toBe('{ filters?: Record<string, unknown>; sorts?: string[]; columns?: string[] } | null');
    });

    test('an unrecognized generic container degrades to the pre-existing vague type instead of partial-matching', function () {
        // 'tags' is tagged `@property LengthAwarePaginator<int, OrderItem>|null`, a container shape that
        // stays unwrapped; left un-degraded, toTsType()'s partial matching reads the inner "int" as 'number'.
        $info = resolve(ModelAttributeResolver::class)
            ->resolveAttribute(PropertyDocblockEdge::class, 'tags');

        expect($info['type'])->toBe('unknown[] | null');
    });

    test('a different tag\'s description mentioning another column\'s $variable does not bleed into that column\'s type', function () {
        // The fixture's `label` tag reads "@property string $label Falls back to the $related_users value",
        // so a type capture that doesn't stop at '$' claims "string $label Falls back to the" for related_users.
        $info = resolve(ModelAttributeResolver::class)
            ->resolveAttribute(PropertyDocblockEdge::class, 'related_users');

        expect($info['type'])->toBe('unknown[] | null');
    });

    test('a refinement that still names unknown is accepted over an entirely vague original', function () {
        // Team's 'settings' casts to plain 'array' (-> unknown[]); its @property tag types the shape
        // as Record<string, unknown>, which is more structured than the bare unknown[] it replaces.
        $info = resolve(ModelAttributeResolver::class)
            ->resolveAttribute(Team::class, 'settings');

        expect($info['type'])->toBe('Record<string, unknown> | null');
    });

    test('trait class docblocks are consulted after the class/parent chain, tolerating a missing $ sigil', function () {
        // 'labels' is an old-style accessor from the HasLabels trait; only the trait's own class
        // docblock tags it, and it does so without the `$` sigil (a form found in the wild).
        $info = resolve(ModelAttributeResolver::class)
            ->resolveAttribute(PropertyDocblockTraitFixture::class, 'labels');

        expect($info['type'])->toBe('string[]');
    });

    test('a $-less tag with a trailing description does not bind an unrelated attribute matching its last word', function () {
        // The trait's tag reads "@property string[] tag_names Friendly labels list" (no $). Search for
        // 'list' — an unrelated real accessor whose name is coincidentally the description's last word.
        // A type capture unbounded by the no-description restriction can walk all the way to "list" and
        // mistake it for the tag's own property name, producing a bogus concrete type instead of unknown[].
        $info = resolve(ModelAttributeResolver::class)
            ->resolveAttribute(PropertyDocblockDescribedTagFixture::class, 'list');

        expect($info['type'])->toBe('unknown[]');
    });

    test('a refinement that is itself vague-but-not-entirely-vague never replaces an already-structured vague type', function () {
        // 'meta_info' casts to Eloquent's Collection -> Record<string, unknown>: vague, but not one of
        // the four "entirely vague" literals (unlike AsArrayObject's own map entry). The
        // class's own @property tag resolves to the differently-vague Record<string, unknown[]> (also
        // not one of the four) — isEntirelyVagueTsType(current) is false for both sides, so
        // isStrictlyMoreStructured() must reject and keep the Collection-derived type.
        $info = resolve(ModelAttributeResolver::class)
            ->resolveAttribute(PropertyDocblockRejectFixture::class, 'meta_info');

        expect($info['type'])->toBe('Record<string, unknown> | null');
    });
});

test('an AsArrayObject cast resolves to the array-or-record union', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    $result = $resolver->resolveAttribute(ArrayObjectCastFixture::class, 'owner_snapshot');

    expect($result['type'])->toBe('unknown[] | Record<string, unknown> | null');
});

describe('write-only accessor waterfall', function () {
    test('a set-only mutator with a documented Get generic resolves to that type', function () {
        // Order::trackingCode has no getter closure, but its docblock still names Attribute<?string, string>.
        $info = resolve(ModelAttributeResolver::class)
            ->resolveAttribute(Order::class, 'tracking_code');

        expect($info['type'])->toBe('string | null');
    });

    test('a set-only mutator backed by a real column resolves through the DB waterfall', function () {
        // Profile::normalizedPhone has no getter and no docblock generic, but 'normalized_phone' is a real column.
        $info = resolve(ModelAttributeResolver::class)
            ->resolveAttribute(Profile::class, 'normalized_phone');

        expect($info['type'])->toBe('string | null');
    });

    test('a set-only mutator with no docblock generic and no backing column resolves to unknown', function () {
        // Order::searchIndex has neither a getter, a docblock generic, nor a matching DB column.
        $info = resolve(ModelAttributeResolver::class)
            ->resolveAttribute(Order::class, 'search_index');

        expect($info['type'])->toBe('unknown');
    });
});

describe('castable-with-arguments casts', function () {
    test('AsEnumCollection::of(WeekDays) resolves through the waterfall to a nullable enum array', function () {
        $info = resolve(ModelAttributeResolver::class)
            ->resolveAttribute(Team::class, 'week_days');

        expect($info['type'])->toBe('WeekDaysType[] | null');
    });

    test('AsCollection::of(GridConfigDto) resolves through the waterfall to a nullable shape array', function () {
        $info = resolve(ModelAttributeResolver::class)
            ->resolveAttribute(Team::class, 'grid_configs');

        expect($info['type'])->toBe('{ label: string; config: Record<string, unknown> }[] | null');
    });
});

describe('attribute-lookup fallbacks', function () {
    test('resolves {relation}_count virtual attribute (withCount) to number', function () {
        $info = resolve(ModelAttributeResolver::class)
            ->resolveAttribute(Order::class, 'items_count');

        expect($info['type'])->toBe('number');
    });

    test('resolves {relation}_exists virtual attribute (withExists) to boolean', function () {
        $info = resolve(ModelAttributeResolver::class)
            ->resolveAttribute(Order::class, 'items_exists');

        expect($info['type'])->toBe('boolean');
    });

    test('resolves camelCase access to a snake_case accessor', function () {
        // Eloquent's __get() resolves $this->formattedTotal to the 'formatted_total' accessor at runtime.
        $info = resolve(ModelAttributeResolver::class)
            ->resolveAttribute(Order::class, 'formattedTotal');

        expect($info['type'])->toBe('string');
    });

    test('a real column ending in _count resolves through the normal waterfall, not the suffix fallback', function () {
        // Post::word_count is a real nullable integer column, not a withCount() virtual attribute.
        $info = resolve(ModelAttributeResolver::class)
            ->resolveAttribute(Post::class, 'word_count');

        expect($info['type'])->toBe('number | null');
    });

    test('{relation}_count fallback does not fire when no matching relation exists', function () {
        $info = resolve(ModelAttributeResolver::class)
            ->resolveAttribute(Order::class, 'bogus_count');

        expect($info['type'])->toBe('unknown');
    });

    test('{relation}_exists fallback does not fire when no matching relation exists', function () {
        $info = resolve(ModelAttributeResolver::class)
            ->resolveAttribute(Order::class, 'bogus_exists');

        expect($info['type'])->toBe('unknown');
    });

    test('camelCase access to a plain column stays unknown, matching its null runtime value', function () {
        // Eloquent camel-cases the key only when hunting for a mutator; $order->placedAt is always null.
        $info = resolve(ModelAttributeResolver::class)
            ->resolveAttribute(Order::class, 'placedAt');

        expect($info['type'])->toBe('unknown');
    });

    test('camelCase fallback does not fire when no matching snake_case attribute exists', function () {
        $info = resolve(ModelAttributeResolver::class)
            ->resolveAttribute(Order::class, 'totallyMadeUpAttribute');

        expect($info['type'])->toBe('unknown');
    });
});
