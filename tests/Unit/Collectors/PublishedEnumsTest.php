<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Collectors\PublishedEnums;
use Workbench\App\Enums\ExcludedEnum;
use Workbench\App\Enums\Grade;

beforeEach(function () {
    PublishedEnums::flush();
});

it('includes a collected enum and excludes a #[TsExclude] one', function () {
    expect(PublishedEnums::includes(Grade::class))->toBeTrue()
        ->and(PublishedEnums::includes(ExcludedEnum::class))->toBeFalse();
});

it('forgets its answer when the enums config changes', function () {
    expect(PublishedEnums::includes(Grade::class))->toBeTrue();

    config()->set('ts-publish.enums.excluded', [Grade::class]);

    expect(PublishedEnums::includes(Grade::class))->toBeFalse();
});

it('includes nothing while enum publishing is off', function () {
    config()->set('ts-publish.enums.enabled', false);

    expect(PublishedEnums::includes(Grade::class))->toBeFalse();
});
