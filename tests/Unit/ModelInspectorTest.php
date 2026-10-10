<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Collectors\ModelsCollector;
use AbeTwoThree\LaravelTsPublish\ModelInspector;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\UnreadableRelationFacility;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\UnreadableRelationTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Workbench\App\Packages\Audit\Models\AuditNote;

describe('ModelInspector with a relation that cannot be read on a blank model', function () {
    test('relationsOf() leaves out each relation that throws, reads the others, and warns of the one not excluded', function () {
        $relations = resolve(ModelInspector::class)->relationsOf(new UnreadableRelationTrail);

        expect($relations->all())->toBe([
            ['name' => 'facility', 'type' => 'BelongsTo', 'related' => UnreadableRelationFacility::class],
            ['name' => 'trailNotes', 'type' => 'HasMany', 'related' => AuditNote::class],
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

test('reading one method at a time gives Laravel\'s list for every collected model', function () {
    $inspector = resolve(ModelInspector::class);
    $oneByOne = Closure::bind(fn (Model $model): Collection => $this->relationsOneByOne($model), $inspector, ModelInspector::class);

    $models = resolve(ModelsCollector::class)->collect();
    $differing = $models->reject(
        fn (string $model): bool => $oneByOne(new $model)->all() === $inspector->relationsOf(new $model)->all(),
    );

    // A list this long proves the walk ran: an empty collection would pass every comparison.
    expect($models->count())->toBeGreaterThan(50)
        ->and($differing->values()->all())->toBe([]);
});
