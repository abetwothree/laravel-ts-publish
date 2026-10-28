<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Collectors\ModelsCollector;
use AbeTwoThree\LaravelTsPublish\ModelInspector;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\DeclaredRelationCategory;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ReviewedCategory;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\TreeCategory;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\UnreadableRelationFacility;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\UnreadableRelationTrail;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Relations\Ancestors;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Relations\Descendants;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Relations\FirstRelated;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Relations\Siblings;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelInspector as EloquentModelInspector;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Workbench\App\Models\Category;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\Packages\Audit\Models\AuditNote;

describe('ModelInspector with a relation that cannot be read on a blank model', function () {
    test('relationsOf() leaves out each relation that throws, reads the others, and warns of the one not excluded', function () {
        $relations = resolve(ModelInspector::class)->relationsOf(new UnreadableRelationTrail);

        expect($relations->all())->toBe([
            ['name' => 'facility', 'type' => 'BelongsTo', 'related' => UnreadableRelationFacility::class, 'class' => BelongsTo::class],
            ['name' => 'trailNotes', 'type' => 'HasMany', 'related' => AuditNote::class, 'class' => HasMany::class],
        ])
            ->and(AnalysisWarnings::all())->toBe([[
                'subject' => UnreadableRelationTrail::class,
                'message' => 'Reading its notes() relation threw [Attempt to read property "name" on null], '
                    .'so the relation is left out.',
            ]]);
    });

    test('inspect() reads the same relations, and a second read adds no second warning', function () {
        $inspector = resolve(ModelInspector::class);
        $inspector->relationsOf(new UnreadableRelationTrail);

        $relations = $inspector->inspect(UnreadableRelationTrail::class)->relations;

        expect($relations->pluck('name')->all())->toBe(['facility', 'trailNotes'])
            ->and(AnalysisWarnings::all())->toHaveCount(1);
    });
});

describe('ModelInspector with a relation method declared only by its @return relation class', function () {
    test('discovers a relation method declared only by its @return relation class', function () {
        expect(resolve(ModelInspector::class)->relationsOf(new TreeCategory)->all())->toBe([
            ['name' => 'firstBranch', 'type' => 'FirstRelated', 'related' => TreeCategory::class, 'class' => FirstRelated::class],
            ['name' => 'descendants', 'type' => 'Descendants', 'related' => TreeCategory::class, 'class' => Descendants::class],
            ['name' => 'siblings', 'type' => 'Siblings', 'related' => TreeCategory::class, 'class' => Siblings::class],
            ['name' => 'ancestors', 'type' => 'Ancestors', 'related' => TreeCategory::class, 'class' => Ancestors::class],
            ['name' => 'parent', 'type' => 'BelongsTo', 'related' => Category::class, 'class' => BelongsTo::class],
            ['name' => 'children', 'type' => 'HasMany', 'related' => Category::class, 'class' => HasMany::class],
            ['name' => 'posts', 'type' => 'HasMany', 'related' => Post::class, 'class' => HasMany::class],
        ])
            ->and((new ModelTransformer(TreeCategory::class))->data()->relations['ancestors']['type'])->toBe('TreeCategory[]');
    });

    test('reads no method whose @return names another class or that takes a parameter, and leaves out one that throws', function () {
        AnalysisWarnings::reset();

        $relations = resolve(ModelInspector::class)->relationsOf(new DeclaredRelationCategory);

        expect($relations->pluck('class', 'name')->all())->toBe([
            'parent' => BelongsTo::class,
            'ancestors' => Ancestors::class,
        ])
            ->and(AnalysisWarnings::all())->toBe([[
                'subject' => DeclaredRelationCategory::class,
                'message' => 'Reading its lineage() relation threw [Attempt to read property "slug" on null], '
                    .'so the relation is left out.',
            ]]);
    });

    test('declaresRelationReturn() takes a parameterless method whose declared return names only relation classes', function () {
        $declares = fn (string $class, string $method): bool => resolve(ModelInspector::class)
            ->declaresRelationReturn(new ReflectionMethod($class, $method));

        expect($declares(DeclaredRelationCategory::class, 'ancestors'))->toBeTrue()
            ->and($declares(DeclaredRelationCategory::class, 'lineage'))->toBeTrue()
            ->and($declares(DeclaredRelationCategory::class, 'parent'))->toBeTrue()
            ->and($declares(DeclaredRelationCategory::class, 'labels'))->toBeFalse()
            ->and($declares(DeclaredRelationCategory::class, 'ancestorsUpTo'))->toBeFalse()
            ->and($declares(TreeCategory::class, 'branches'))->toBeFalse()
            ->and($declares(Model::class, 'newQuery'))->toBeFalse();
    });
});

// PHP 8.4 binds a class's traits after its parent, so get_class_methods() lists a trait's methods after the inherited
// ones there, and a trait method that redeclares an inherited one in the inherited one's place.
describe('ModelInspector relation order', function () {
    test('lists a trait relation that redeclares an inherited one in the trait\'s own order', function () {
        expect(resolve(ModelInspector::class)->relationsOf(new ReviewedCategory)->all())->toBe([
            ['name' => 'reviewer', 'type' => 'BelongsTo', 'related' => User::class, 'class' => BelongsTo::class],
            ['name' => 'parent', 'type' => 'BelongsTo', 'related' => Category::class, 'class' => BelongsTo::class],
            ['name' => 'children', 'type' => 'HasMany', 'related' => Category::class, 'class' => HasMany::class],
            ['name' => 'posts', 'type' => 'HasMany', 'related' => Post::class, 'class' => HasMany::class],
        ]);
    });
});

// staudenmeir/eloquent-has-many-deep's HasOneDeep is built the same way, on has-many-through.
test('publishes a relation class built on HasMany that loads one model as that model or null, as Laravel sends it', function () {
    $root = TreeCategory::create(['name' => 'Root', 'slug' => 'root']);
    $branch = TreeCategory::create(['name' => 'Branch', 'slug' => 'branch', 'parent_id' => $root->id]);

    $sent = fn (TreeCategory $category): mixed => TreeCategory::with('firstBranch')->findOrFail($category->id)
        ->toArray()['first_branch'];

    expect((new ModelTransformer(TreeCategory::class))->data()->relations['first_branch']['type'])->toBe('TreeCategory | null')
        ->and($sent($root))->toBeArray()->toHaveKey('id', $branch->id)
        ->and($sent($branch))->toBeNull()
        ->and($root->firstBranch)->toBeInstanceOf(TreeCategory::class);
});

// staudenmeir/laravel-adjacency-list's Descendants replaces match(), and its Siblings getResults() and match().
test('publishes a relation class built on HasMany that replaces how it loads but still loads a list as a list, as Laravel sends it', function (string $relation) {
    $root = TreeCategory::create(['name' => 'Root', 'slug' => 'root']);
    $branch = TreeCategory::create(['name' => 'Branch', 'slug' => 'branch', 'parent_id' => $root->id]);

    $sent = fn (TreeCategory $category): mixed => TreeCategory::with($relation)->findOrFail($category->id)
        ->toArray()[$relation];

    expect((new ModelTransformer(TreeCategory::class))->data()->relations[$relation]['type'])->toBe('TreeCategory[]')
        ->and($sent($root))->toBeList()->toHaveCount(1)
        ->and($sent($root)[0])->toHaveKey('id', $branch->id)
        ->and($sent($branch))->toBe([])
        ->and($root->{$relation})->toBeInstanceOf(EloquentCollection::class);
})->with(['descendants', 'siblings']);

test('Laravel loads, counts and sends the notification relations only their docblock declares', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);

    $sent = User::with(['readNotifications', 'unreadNotifications'])
        ->withCount('readNotifications')
        ->withExists('unreadNotifications')
        ->findOrFail($user->getKey())
        ->toArray();

    expect(Arr::only($sent, ['read_notifications_count', 'unread_notifications_exists', 'read_notifications', 'unread_notifications']))
        ->toBe([
            'read_notifications_count' => 0,
            'unread_notifications_exists' => false,
            'read_notifications' => [],
            'unread_notifications' => [],
        ]);
});

test('reads Laravel\'s relation list for every collected model, adding only the relations a docblock declares', function () {
    $inspector = resolve(ModelInspector::class);
    $laravelRelations = Closure::bind(
        fn (Model $model): Collection => $this->getRelations($model),
        new EloquentModelInspector(app()),
        EloquentModelInspector::class,
    );

    $models = resolve(ModelsCollector::class)->collect();
    $differing = [];
    $added = [];

    foreach ($models as $model) {
        $laravel = $laravelRelations(new $model)->all();
        $read = $inspector->relationsOf(new $model)->map(fn (array $relation): array => Arr::except($relation, 'class'));
        $names = array_column($laravel, 'name');

        if ($read->whereIn('name', $names)->values()->all() !== $laravel) {
            $differing[] = $model;
        }

        foreach ($read->whereNotIn('name', $names) as $relation) {
            $added[] = $model.'::'.$relation['name'];
        }
    }

    // A list this long proves the walk ran: an empty collection would pass every comparison.
    expect($models->count())->toBeGreaterThan(50)
        ->and($differing)->toBe([])
        ->and($added)->toBe([User::class.'::readNotifications', User::class.'::unreadNotifications']);
});
