<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Cache\PublishedModelRegistry;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\LaravelTsPublish as LaravelTsPublishService;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\ModelInspector;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\AliasedSubjectModel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CastablePost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CountingCastable;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\DriverOverrideModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\FacilityAlias;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\MissingTableModel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\MorphPivot\InvalidPivotClassParent;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\MorphPivot\InverseMorphToManyParent;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\MorphPivot\NotAModelPivot;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReceiverAttributeBaseModel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReceiverAttributeChildModel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\RecordingModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\UncastDecimalOrderItem;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\UnconstructableModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverChildDto;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\RelationHiddenUser;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\RelationKeyCaseUser;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\RelationVisibilityUser;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\VisibleFilterOverrideModel;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\ShirtSize;
use Workbench\App\Models\Activity;
use Workbench\App\Models\Address;
use Workbench\App\Models\Admin\Store;
use Workbench\App\Models\ArrayObjectCastFixture;
use Workbench\App\Models\Artist;
use Workbench\App\Models\ArtistReview;
use Workbench\App\Models\Attachment;
use Workbench\App\Models\Category;
use Workbench\App\Models\Comment;
use Workbench\App\Models\CompositeComment;
use Workbench\App\Models\DocblockGenericsFixture;
use Workbench\App\Models\ExcludedModel;
use Workbench\App\Models\Facility;
use Workbench\App\Models\Image;
use Workbench\App\Models\Kpi;
use Workbench\App\Models\Label;
use Workbench\App\Models\Labelable;
use Workbench\App\Models\Marketing\Report\Report as MarketingReport;
use Workbench\App\Models\Order;
use Workbench\App\Models\OrderItem;
use Workbench\App\Models\OutgoingNote;
use Workbench\App\Models\Post;
use Workbench\App\Models\Product;
use Workbench\App\Models\Profile;
use Workbench\App\Models\PropertyDocblockBase;
use Workbench\App\Models\PropertyDocblockChild;
use Workbench\App\Models\PropertyDocblockDescribedTagFixture;
use Workbench\App\Models\PropertyDocblockEdge;
use Workbench\App\Models\PropertyDocblockRejectFixture;
use Workbench\App\Models\PropertyDocblockTraitFixture;
use Workbench\App\Models\Review;
use Workbench\App\Models\Sales\Report\Report as SalesReport;
use Workbench\App\Models\Team;
use Workbench\App\Models\TrackingEvent;
use Workbench\App\Models\User;
use Workbench\App\Models\Venue;
use Workbench\App\Models\VenueReview;
use Workbench\App\Models\Warehouse;
use Workbench\App\Packages\Audit\Models\AuditArchive;
use Workbench\App\Packages\Audit\Models\AuditInspector;
use Workbench\App\Packages\Audit\Models\AuditNote;
use Workbench\App\Packages\Audit\Models\AuditTrail;
use Workbench\App\ValueObjects\Coordinate;

describe('resolveAttributeClass()', function () {
    test('an enum cast holds its enum and a date cast holds Carbon', function () {
        $resolver = resolve(ModelAttributeResolver::class);

        expect($resolver->resolveAttributeClass(Post::class, 'priority'))->toBe(Priority::class)
            ->and($resolver->resolveAttributeClass(Post::class, 'published_at'))->toBe(Carbon::class);
    });

    test('a CastsAttributes cast holds its get() return class', function () {
        expect(resolve(ModelAttributeResolver::class)->resolveAttributeClass(Warehouse::class, 'coordinate_data'))
            ->toBe(Coordinate::class);
    });

    test('an accessor holds its getter closure class, else its Attribute docblock Get class', function () {
        $resolver = resolve(ModelAttributeResolver::class);

        expect($resolver->resolveAttributeClass(Image::class, 'shirt_size'))->toBe(ShirtSize::class)
            ->and($resolver->resolveAttributeClass(Post::class, 'latest_comment'))->toBe(Comment::class)
            ->and($resolver->resolveAttributeClass(Image::class, 'uploader_from_docblock'))->toBe(User::class)
            ->and($resolver->resolveAttributeClass(Image::class, 'uploaders_from_docblock'))->toBe(Collection::class);
    });

    test('an old-style accessor holds its native return class', function () {
        expect(resolve(ModelAttributeResolver::class)->resolveAttributeClass(TrackingEvent::class, 'changes'))
            ->toBe(Collection::class);
    });

    test('a scalar column, a scalar accessor, and an unknown name hold no class', function () {
        $resolver = resolve(ModelAttributeResolver::class);

        expect($resolver->resolveAttributeClass(Post::class, 'title'))->toBeNull()
            ->and($resolver->resolveAttributeClass(Image::class, 'is_landscape'))->toBeNull()
            ->and($resolver->resolveAttributeClass(Post::class, 'options'))->toBeNull()
            ->and($resolver->resolveAttributeClass(ArrayObjectCastFixture::class, 'owner_snapshot'))->toBeNull()
            ->and($resolver->resolveAttributeClass(Post::class, 'no_such_attribute'))->toBeNull()
            ->and($resolver->resolveAttributeClass('Workbench\\App\\Models\\NoSuchModel', 'title'))->toBeNull();
    });
});

describe('resolveAttributeClass() edge cases', function () {
    test('an immutable date cast holds CarbonImmutable; a timestamp cast holds no class', function () {
        $resolver = resolve(ModelAttributeResolver::class);

        expect($resolver->resolveAttributeClass(ReceiverAttributeBaseModel::class, 'published_at'))->toBe(CarbonImmutable::class)
            ->and($resolver->resolveAttributeClass(ReceiverAttributeBaseModel::class, 'deleted_at'))->toBeNull();
    });

    test('a Castable cast is asked for its caster before its own CastsAttributes get()', function () {
        expect(resolve(ModelAttributeResolver::class)->resolveAttributeClass(ReceiverAttributeBaseModel::class, 'content'))
            ->toBe(Coordinate::class);
    });

    // castUsing() is application code with arbitrary side effects, so its caster is read from its declaration and
    // its return statements; each Castable here names CoordinateCast a different way.
    test('a Castable caster is read without ever calling castUsing()', function () {
        CountingCastable::$calls = 0;
        $resolver = resolve(ModelAttributeResolver::class);

        expect($resolver->resolveAttributeClass(CastablePost::class, 'title'))->toBe(Coordinate::class)
            ->and($resolver->resolveAttributeClass(CastablePost::class, 'content'))->toBe(Coordinate::class)
            ->and($resolver->resolveAttributeClass(CastablePost::class, 'metadata'))->toBe(Coordinate::class)
            ->and(CountingCastable::$calls)->toBe(0);
    });

    test('a caster only a call to castUsing() could name holds no class', function () {
        expect(resolve(ModelAttributeResolver::class)->resolveAttributeClass(CastablePost::class, 'options'))->toBeNull();
    });

    test('a native getter type is authoritative even when the Attribute docblock names a class', function () {
        expect(resolve(ModelAttributeResolver::class)->resolveAttributeClass(ReceiverAttributeBaseModel::class, 'typed_label'))
            ->toBeNull();
    });

    test('a getter returning self names its declaring model; one returning static names the model read through', function () {
        $resolver = resolve(ModelAttributeResolver::class);

        expect($resolver->resolveAttributeClass(ReceiverAttributeChildModel::class, 'self_copy'))->toBe(ReceiverAttributeBaseModel::class)
            ->and($resolver->resolveAttributeClass(ReceiverAttributeChildModel::class, 'static_copy'))->toBe(ReceiverAttributeChildModel::class);
    });

    test('a docblock Get naming an array of a generic class holds no class, while the bare generic holds its base', function () {
        $resolver = resolve(ModelAttributeResolver::class);

        expect($resolver->resolveAttributeClass(ReceiverAttributeBaseModel::class, 'collection_list'))->toBeNull()
            ->and($resolver->resolveAttributeClass(Image::class, 'uploaders_from_docblock'))->toBe(Collection::class);
    });

    test('a typed getter closure returning static names the class it was called on', function () {
        expect(resolve(ModelAttributeResolver::class)->resolveAttributeClass(ReceiverAttributeBaseModel::class, 'borrowed_static'))
            ->toBe(ReceiverChildDto::class);
    });

    test('the answer is memoized per model and attribute, including a null answer', function () {
        $resolver = resolve(ModelAttributeResolver::class);
        $resolver->resolveAttributeClass(Post::class, 'priority');
        $resolver->resolveAttributeClass(Post::class, 'title');

        expect(new ReflectionProperty($resolver, 'attributeClassCache')->getValue($resolver))
            ->toBe([Post::class.'::priority' => Priority::class, Post::class.'::title' => null]);
    });
});

describe('resolveMorphToBound()', function () {
    test('a single-model generic is its own bound', function () {
        expect(resolve(ModelAttributeResolver::class)->resolveMorphToBound(Activity::class, 'causer'))->toBe(User::class);
    });

    test('a Model generic, a union generic, and no generic are all bounded by Model', function () {
        $resolver = resolve(ModelAttributeResolver::class);

        expect($resolver->resolveMorphToBound(Kpi::class, 'reportable'))->toBe(Model::class)
            ->and($resolver->resolveMorphToBound(Image::class, 'reviewable'))->toBe(Model::class)
            ->and($resolver->resolveMorphToBound(Image::class, 'imageable'))->toBe(Model::class)
            ->and($resolver->resolveMorphToBound(Image::class, 'noSuchRelation'))->toBe(Model::class);
    });
});

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

describe('delegatedAttributeNames()', function () {
    it('lists $hidden columns while exclude_hidden is off, and not while it is on', function () {
        $resolver = resolve(ModelAttributeResolver::class);

        config()->set('ts-publish.models.exclude_hidden', false);
        expect($resolver->delegatedAttributeNames(User::class))->toContain('password');

        config()->set('ts-publish.models.exclude_hidden', true);
        expect($resolver->delegatedAttributeNames(User::class))->not->toContain('password');
    });

    it('lists an appended accessor, and not one the model does not append', function () {
        expect(resolve(ModelAttributeResolver::class)->delegatedAttributeNames(Address::class))
            ->toContain('full_address')
            ->not->toContain('has_coordinates');
    });

    // VisibleFilterOverrideModel appends `name`, a column, and hides `color`, which `$visible` lists.
    it('lists the columns, then the appends, each name once, past $visible and $hidden only while exclude_hidden is off', function (bool $excludeHidden, array $names) {
        config()->set('ts-publish.models.exclude_hidden', $excludeHidden);

        expect(resolve(ModelAttributeResolver::class)->delegatedAttributeNames(VisibleFilterOverrideModel::class))
            ->toBe($names);
    })->with([
        'exclude_hidden off' => [false, ['id', 'name', 'slug', 'color', 'created_at', 'updated_at', 'label', 'shade']],
        'exclude_hidden on' => [true, ['id', 'name', 'label']],
    ]);
});

// relationsToArray() writes a loaded relation under its name, snake-cased while $snakeAttributes is on, and
// getArrayableRelations() matches `$visible` and `$hidden` against the method name; exclude_hidden gates both lists.
describe('delegatedRelationKeys()', function () {
    $userKeys = [
        'profile' => 'profile', 'posts' => 'posts', 'comments' => 'comments', 'orders' => 'orders',
        'addresses' => 'addresses', 'primaryAddress' => 'primary_address', 'teams' => 'teams',
        'ownedTeams' => 'owned_teams', 'images' => 'images', 'notifications' => 'notifications',
    ];

    it('keys each relation as toArray() writes it', function (string $model, bool $excludeHidden, array $keys) {
        config()->set('ts-publish.models.exclude_hidden', $excludeHidden);

        expect(resolve(ModelAttributeResolver::class)->delegatedRelationKeys($model))->toBe($keys);
    })->with([
        'snake-cased' => [User::class, false, $userKeys],
        'snake-cased, no relation hidden, exclude_hidden on' => [User::class, true, $userKeys],
        '$snakeAttributes off' => [RelationKeyCaseUser::class, false, [
            'profile' => 'profile', 'posts' => 'posts', 'comments' => 'comments', 'orders' => 'orders',
            'addresses' => 'addresses', 'primaryAddress' => 'primaryAddress', 'teams' => 'teams',
            'ownedTeams' => 'ownedTeams', 'images' => 'images', 'notifications' => 'notifications',
        ]],
        '$visible, exclude_hidden off' => [RelationVisibilityUser::class, false, $userKeys],
        '$visible names the method, exclude_hidden on' => [RelationVisibilityUser::class, true, ['ownedTeams' => 'owned_teams']],
        '$hidden, exclude_hidden off' => [RelationHiddenUser::class, false, $userKeys],
        '$hidden names the method, exclude_hidden on' => [RelationHiddenUser::class, true, [
            'profile' => 'profile', 'posts' => 'posts', 'comments' => 'comments', 'orders' => 'orders',
            'addresses' => 'addresses', 'primaryAddress' => 'primary_address', 'teams' => 'teams', 'images' => 'images',
            'notifications' => 'notifications',
        ]],
    ]);
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

test('a resolver overriding buildMorphTargetMap() with its one parameter builds the map through the override', function () {
    $resolver = new RecordingModelAttributeResolver;
    $resolver->buildMorphTargetMap([Post::class, Attachment::class]);

    expect($resolver->morphTargetMapBuilds)->toBe([[Post::class, Attachment::class]])
        ->and($resolver->resolveMorphToTargets(Attachment::class, 'attachable'))->toBe([Post::class]);
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
        // causer() is a morphTo declared in the HasRelatableLinkedRecord trait, not on the model, with no reverse
        // morphMany anywhere pointing at Activity — only the docblock generic can type it.
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

test('getMorphToTargets unions parents that target subclasses of the child', function () {
    $resolver = resolve(ModelAttributeResolver::class);
    $resolver->buildMorphTargetMap([Venue::class, Artist::class, Review::class, VenueReview::class, ArtistReview::class]);

    expect($resolver->getMorphToTargets(Review::class, 'reviewable'))->toBe([Artist::class, Venue::class])
        ->and($resolver->getMorphToTargets(VenueReview::class, 'reviewable'))->toBe([Venue::class])
        ->and($resolver->resolveRelation(Review::class, 'reviewable')['type'])->toBe('Artist | Venue');
});

test('buildMorphTargetMap maps a morphToMany pivot model back to its declaring parents', function () {
    $resolver = resolve(ModelAttributeResolver::class);
    $resolver->buildMorphTargetMap([Venue::class, Artist::class, Label::class, Labelable::class]);

    expect($resolver->getMorphToTargets(Labelable::class, 'labelable'))->toBe([Artist::class, Venue::class]);
});

test('a using() pointing at a non-Model class adds no pivot map entry', function () {
    $resolver = resolve(ModelAttributeResolver::class);
    $resolver->buildMorphTargetMap([InvalidPivotClassParent::class]);

    expect($resolver->getMorphToTargets(NotAModelPivot::class, 'labelable'))->toBe([]);
});

test('the morphedByMany inverse side of a custom pivot adds no pivot map entry', function () {
    $resolver = resolve(ModelAttributeResolver::class);
    $resolver->buildMorphTargetMap([InverseMorphToManyParent::class]);

    expect($resolver->getMorphToTargets(Labelable::class, 'labelable'))->toBe([]);
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
    // the @return parser must not resolve the bare word to Eloquent's own Attribute class. Both are
    // vague, so the getter body types it from the relation it returns.
    $info = resolve(ModelAttributeResolver::class)
        ->resolveAttribute(Order::class, 'unsorted_items');

    expect($info['type'])->not->toBe('Attribute')
        ->and($info['type'])->toBe('OrderItem[]')
        ->and($info['classFqcns'])->toBe([OrderItem::class]);
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

    test('a set-only mutator with no docblock generic and no backing column resolves to unknown and is omitted', function () {
        // Order::searchIndex has neither a getter, a docblock generic, nor a matching DB column. The
        // type alone can't tell "should be omitted" apart from "genuinely unresolvable" — both read
        // 'unknown' — so isOmittedMutator() (reads the 'omit' flag resolveAttribute() discards) pins it.
        $resolver = resolve(ModelAttributeResolver::class);
        $info = $resolver->resolveAttribute(Order::class, 'search_index');

        expect($info['type'])->toBe('unknown')
            ->and($resolver->isOmittedMutator(Order::class, 'search_index'))->toBeTrue();
    });

    test('a set-only mutator whose docblock Get is never resolves to its real column type', function () {
        // OutgoingNote::subject/type are Attribute::set()/make() mutators with no getter, documented
        // `Attribute<never, string>` — the never only records that no getter exists. Reading either
        // attribute returns the raw column value, so the column's own type must win, not a literal 'never'.
        $resolver = resolve(ModelAttributeResolver::class);

        expect($resolver->resolveAttribute(OutgoingNote::class, 'subject')['type'])->toBe('string')
            ->and($resolver->resolveAttribute(OutgoingNote::class, 'type')['type'])->toBe('string');
    });

    test('a set-only mutator whose docblock Get is never and has no backing column resolves to unknown, not never, and is omitted', function () {
        // OutgoingNote::normalizedTag has no backing column, so it stays omitted rather than leaking the docblock's
        // 'never'; resolveAttribute()'s 'unknown' cannot tell omission from an unresolvable attribute, so
        // isOmittedMutator(), which reads the 'omit' flag, pins it too.
        $resolver = resolve(ModelAttributeResolver::class);
        $info = $resolver->resolveAttribute(OutgoingNote::class, 'normalized_tag');

        expect($info['type'])->toBe('unknown')
            ->and($resolver->isOmittedMutator(OutgoingNote::class, 'normalized_tag'))->toBeTrue();
    });

    test('a set-only mutator whose docblock Get is a nullable never resolves to its real column type', function () {
        // OutgoingNote::channel is documented `Attribute<?never, ?string>`, and resolveDocblockTypePartOrAlias() turns
        // `?never` into 'never | null' before the set-only branch, so that spelling must be caught as a bare 'never'.
        $info = resolve(ModelAttributeResolver::class)
            ->resolveAttribute(OutgoingNote::class, 'channel');

        expect($info['type'])->toBe('string');
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

test('resolveContext warns when a model table does not exist', function () {
    AnalysisWarnings::reset();

    resolve(ModelAttributeResolver::class)->resolveAttribute(MissingTableModel::class, 'anything');

    expect(AnalysisWarnings::all())->toHaveCount(1)
        ->and(AnalysisWarnings::all()[0]['subject'])->toBe(MissingTableModel::class)
        ->and(AnalysisWarnings::all()[0]['message'])->toContain('table_that_was_never_migrated');
});

test('resolveContext warns only once per model per run, because the context is cached', function () {
    AnalysisWarnings::reset();

    $resolver = resolve(ModelAttributeResolver::class);
    $resolver->resolveAttribute(MissingTableModel::class, 'anything');
    $resolver->resolveAttribute(MissingTableModel::class, 'something_else');

    expect(AnalysisWarnings::all())->toHaveCount(1);
});

describe('resolveAttribute() @property fallback for virtual attributes', function () {
    test('types a query-selected attribute from its @property tag', function () {
        expect(resolve(ModelAttributeResolver::class)->resolveAttribute(DocblockGenericsFixture::class, 'children_total')['type'])
            ->toBe('number | null');
    });

    test('never answers a relation name from an ide-helper @property-read tag', function () {
        expect(resolve(ModelAttributeResolver::class)->resolveAttribute(DocblockGenericsFixture::class, 'child_rows')['type'])
            ->toBe('unknown');
    });
});

describe('models this run does not publish', function () {
    test('resolveRelation names a related model while there is no published set to read', function () {
        expect(resolve(ModelAttributeResolver::class)->resolveRelation(Comment::class, 'post'))
            ->toBe(['type' => 'Post', 'modelFqcn' => Post::class, 'morphFqcns' => []]);
    });

    test('resolveRelation names nothing for a related model outside the published set', function () {
        PublishedModelRegistry::register([Comment::class]);

        expect(resolve(ModelAttributeResolver::class)->resolveRelation(Comment::class, 'post'))
            ->toBe(['type' => 'unknown', 'modelFqcn' => null, 'morphFqcns' => []]);
    });

    test('a morphTo docblock generic keeps only the targets in the published set', function () {
        PublishedModelRegistry::register([Image::class, User::class]);

        // Image::reviewable() is documented as MorphTo<Crm\User|User>, and Crm's User is outside the set.
        $result = resolve(ModelAttributeResolver::class)->resolveRelation(Image::class, 'reviewable');

        expect($result['type'])->toBe('User | null')
            ->and($result['morphFqcns'])->toBe([User::class]);
    });

    test('the reverse morph map keeps only the targets in the published set', function () {
        $resolver = resolve(ModelAttributeResolver::class);
        $resolver->buildMorphTargetMap([User::class, Post::class, Product::class, Image::class]);

        PublishedModelRegistry::register([User::class, Image::class]);

        expect($resolver->resolveMorphToTargets(Image::class, 'imageable'))->toBe([User::class]);
    });
});

describe('withRelatedModels()', function () {
    test('adds each accepted model a relation reaches, transitively, sorted after the given ones', function () {
        $related = resolve(ModelAttributeResolver::class)->withRelatedModels(
            [Facility::class],
            fn (string $class): bool => str_starts_with($class, 'Workbench\\App\\Packages\\Audit\\'),
        );

        // AuditNote is reached only through AuditTrail, and AuditInspector only through a morphTo docblock generic.
        expect($related)->toBe([
            Facility::class,
            AuditArchive::class,
            AuditInspector::class,
            AuditNote::class,
            AuditTrail::class,
        ]);
    });

    test('asks about each candidate once, and never about a model it was given', function () {
        $asked = [];

        resolve(ModelAttributeResolver::class)->withRelatedModels(
            [Facility::class, AuditTrail::class],
            function (string $class) use (&$asked): bool {
                $asked[] = $class;

                return str_starts_with($class, 'Workbench\\App\\Packages\\Audit\\');
            },
        );

        sort($asked);

        expect($asked)->toBe([
            ExcludedModel::class,
            User::class,
            AuditArchive::class,
            AuditInspector::class,
            AuditNote::class,
        ]);
    });

    test('leaves out, with one warning, a model reached whose context cannot be read at all', function (bool $withoutTables) {
        $inspector = new class(app()) extends ModelInspector
        {
            public function relationsOf(Model $model): Collection
            {
                if ($model instanceof AuditTrail) {
                    throw new RuntimeException('The trail store is offline.');
                }

                return parent::relationsOf($model);
            }

            public function inspect($model, $connection = null): Arrayable
            {
                if ($model === AuditTrail::class) {
                    throw new RuntimeException('The trail store is offline.');
                }

                return parent::inspect($model, $connection);
            }
        };
        app()->instance(ModelInspector::class, $inspector);

        $related = (new ModelAttributeResolver)->withRelatedModels(
            [Facility::class],
            fn (string $class): bool => str_starts_with($class, 'Workbench\\App\\Packages\\Audit\\'),
            $withoutTables,
        );

        // AuditArchive and AuditNote are reached only through AuditTrail, so neither is reached at all.
        expect($related)->toBe([Facility::class, AuditInspector::class])
            ->and(AnalysisWarnings::all())->toBe([[
                'subject' => AuditTrail::class,
                'message' => 'Is reached through a relation, but inspecting it threw [The trail store is offline.], '
                    .'so it is not published and no generated file names it.',
            ]]);
    })->with(['reading tables' => [false], 'reading no table' => [true]]);

    test('never follows the relations of a model the filter refused', function () {
        $related = resolve(ModelAttributeResolver::class)->withRelatedModels(
            [Facility::class],
            fn (string $class): bool => $class === AuditInspector::class,
        );

        // AuditTrail was refused, so AuditNote behind it is never reached.
        expect($related)->toBe([Facility::class, AuditInspector::class]);
    });
});

describe('reading relations without reading any table', function () {
    test('withRelatedModels() lists the same models as the default read and makes no query', function () {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $accepts = fn (string $class): bool => str_starts_with($class, 'Workbench\\App\\Packages\\Audit\\');

        $withoutTables = (new ModelAttributeResolver)->withRelatedModels([Facility::class], $accepts, withoutTables: true);
        $queriesWithoutTables = $queries;
        $withTables = (new ModelAttributeResolver)->withRelatedModels([Facility::class], $accepts);

        // The default read inspects each model's table, so the zero only means something beside a count above it.
        expect($withoutTables)->toBe($withTables)
            ->and($queriesWithoutTables)->toBe(0)
            ->and($queries)->toBeGreaterThan(0);
    });

    test('buildMorphTargetMapWithoutTables() builds the same map as buildMorphTargetMap() and makes no query', function () {
        $models = [User::class, Post::class, Product::class, Image::class];
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $withoutTables = new ModelAttributeResolver;
        $withoutTables->buildMorphTargetMapWithoutTables($models);
        $queriesWithoutTables = $queries;

        $withTables = new ModelAttributeResolver;
        $withTables->buildMorphTargetMap($models);

        expect($queriesWithoutTables)->toBe(0)
            ->and($withoutTables->resolveMorphToTargets(Image::class, 'imageable'))
            ->toBe($withTables->resolveMorphToTargets(Image::class, 'imageable'))
            ->not->toBeEmpty();
    });

    test('a table-free build that throws leaves the next build reading tables', function () {
        $resolver = new class extends ModelAttributeResolver
        {
            public bool $throws = true;

            public function buildMorphTargetMap(array $modelFqcns): void
            {
                if ($this->throws) {
                    $this->throws = false;

                    throw new RuntimeException('The project\'s override failed.');
                }

                parent::buildMorphTargetMap($modelFqcns);
            }
        };

        expect(fn () => $resolver->buildMorphTargetMapWithoutTables([Image::class]))
            ->toThrow(RuntimeException::class, 'The project\'s override failed.');

        // The first query of a test migrates the lazily refreshed database, so it must not be counted.
        DB::select('select 1');
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $resolver->buildMorphTargetMap([Image::class]);

        expect($queries)->toBeGreaterThan(0);
    });

    test('a class that does not exist or cannot be constructed has no relations to follow', function () {
        $given = ['Workbench\\App\\Models\\NoSuchModel', UnconstructableModel::class];

        $related = (new ModelAttributeResolver)->withRelatedModels($given, fn (): bool => true, withoutTables: true);

        expect($related)->toBe($given);
    });

    test('a model with a cached full context gives its relations from it, and any other from the container\'s inspector', function () {
        $inspector = new class(app()) extends ModelInspector
        {
            public int $relationReads = 0;

            public function relationsOf(Model $model): Collection
            {
                $this->relationReads++;

                return parent::relationsOf($model);
            }
        };
        app()->instance(ModelInspector::class, $inspector);

        $withContext = new ModelAttributeResolver;
        $withContext->getRelations(Facility::class);
        $withContext->withRelatedModels([Facility::class], fn (): bool => false, withoutTables: true);
        $readsWithContext = $inspector->relationReads;

        (new ModelAttributeResolver)->withRelatedModels([Facility::class], fn (): bool => false, withoutTables: true);

        expect($readsWithContext)->toBe(0)
            ->and($inspector->relationReads)->toBe(1);
    });
});

describe('a morphTo docblock that names a class_alias', function () {
    beforeEach(function () {
        // class_alias() cannot be undone, and every test in the process shares the one alias.
        if (! class_exists(FacilityAlias::class)) {
            class_alias(Facility::class, FacilityAlias::class);
        }
    });

    test('withRelatedModels() lists the class it already has once, not again as its alias', function () {
        $related = resolve(ModelAttributeResolver::class)->withRelatedModels(
            [AliasedSubjectModel::class, Facility::class],
            fn (string $class): bool => in_array($class, [Facility::class, FacilityAlias::class], true),
        );

        expect($related)->toBe([AliasedSubjectModel::class, Facility::class]);
    });

    test('resolveRelation() keeps the target the set publishes and names it by the declared class', function () {
        PublishedModelRegistry::register([AliasedSubjectModel::class, Facility::class]);

        $result = resolve(ModelAttributeResolver::class)->resolveRelation(AliasedSubjectModel::class, 'subject');

        expect($result['morphFqcns'])->toBe([Facility::class])
            ->and($result['type'])->toBe('Facility | null');
    });
});

// One rule decides whether a relation can load as null: the one resolveRelation() types its `| null` arm with.
describe('relationLoadsNull()', function () {
    it('tells whether a relation can be loaded as null', function (string $model, string $relation, ?bool $expected) {
        expect(resolve(ModelAttributeResolver::class)->relationLoadsNull($model, $relation))->toBe($expected);
    })->with([
        'a BelongsTo whose foreign key is nullable' => [Category::class, 'parent', true],
        'a BelongsTo whose foreign key is required' => [Comment::class, 'user', false],
        'a HasOne' => [User::class, 'profile', true],
        'a nullable MorphTo' => [Image::class, 'reviewable', true],
        'a required MorphTo' => [Image::class, 'imageable', false],
        'a HasMany' => [User::class, 'posts', false],
        'a relation the model does not declare' => [User::class, 'featuredPosts', null],
    ]);

    it('answers false for every relation while nullable_relations is off', function () {
        config()->set('ts-publish.models.nullable_relations', false);

        expect(resolve(ModelAttributeResolver::class)->relationLoadsNull(Category::class, 'parent'))->toBeFalse()
            ->and(resolve(ModelAttributeResolver::class)->relationLoadsNull(User::class, 'featuredPosts'))->toBeFalse();
    });

    // A loaded to-many relation is a collection, so a nullability map that calls it nullable cannot make it load null.
    it('answers false for a to-many relation its strategy calls nullable', function () {
        config()->set('ts-publish.models.relation_nullability_map', [HasMany::class => 'nullable']);

        expect(resolve(ModelAttributeResolver::class)->relationLoadsNull(User::class, 'posts'))->toBeFalse();
    });
});

// pdo_mysql and pdo_pgsql return a DECIMAL column as a string and pdo_sqlite as a number; an integer column stays put.
it('types an uncast decimal column as the connection\'s driver returns it', function (string $driver, string $decimal) {
    app()->instance(ModelAttributeResolver::class, new DriverOverrideModelAttributeResolver($driver));
    $resolver = resolve(ModelAttributeResolver::class);

    expect($resolver->resolveAttribute(UncastDecimalOrderItem::class, 'unit_price')['type'])->toBe($decimal)
        ->and($resolver->resolveAttribute(UncastDecimalOrderItem::class, 'quantity')['type'])->toBe('number');
})->with([
    'sqlite' => ['sqlite', 'number'],
    'mysql' => ['mysql', 'string'],
    'mariadb' => ['mariadb', 'string'],
    'pgsql' => ['pgsql', 'string'],
    'sqlsrv, which proves no type' => ['sqlsrv', 'number'],
]);

// Only a `number` moves: a date column, which every driver returns as text, keeps the Date timestamps_as_date gives it.
it('moves only a number column to the string its driver returns', function () {
    config()->set('ts-publish.timestamps_as_date', true);
    app()->instance(ModelAttributeResolver::class, new DriverOverrideModelAttributeResolver('mysql'));

    expect(resolve(ModelAttributeResolver::class)->resolveAttribute(UncastDecimalOrderItem::class, 'created_at')['type'])
        ->toBe('Date | null');
});
