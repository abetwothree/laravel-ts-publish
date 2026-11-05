<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Workbench\App\Enums\Grade;
use Workbench\App\Enums\Status;

/** Helpers whose vague declarations leave the enum cases they return or map to the body fallback. */
final class EnumCaseHelperService
{
    /** A case behind a mixed declaration. */
    public function draft(): mixed
    {
        return Status::Draft;
    }

    /** The case another vague helper returns. */
    public function outer(): mixed
    {
        return $this->draft();
    }

    /** A wrap of the case another vague helper returns. */
    public function outerWrap(): mixed
    {
        return EnumResource::make($this->outer());
    }

    /** A grade's values, mapped by an untyped array_map() callback. */
    public function mappedGrades(): array
    {
        return ['m' => array_map(fn ($g) => $g->value, Grade::cases())];
    }

    /** A status's values, mapped by an untyped collect()->map() callback. */
    public function collectedStatuses(): array
    {
        return ['m' => collect(Status::cases())->map(fn ($s) => $s->value)->all()];
    }

    /** A grade's cases. */
    public function gradeCases(): array
    {
        return ['m' => Grade::cases()];
    }

    /** A case mapped to itself, so the callback reads no member of it. */
    public function identity(): array
    {
        return ['m' => collect([Status::Draft])->map(fn ($s) => $s)->all()];
    }
}
