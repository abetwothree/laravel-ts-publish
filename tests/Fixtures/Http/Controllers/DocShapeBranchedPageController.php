<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Person;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\User;

/** A test-only controller whose render branches join a props helper's override-beside-model fill with a null. */
class DocShapeBranchedPageController
{
    /** Renders the props helper's fill on one branch and a null owner on the other. */
    public function branched(int $id): Response
    {
        if ($id > 1) {
            return Inertia::render('DocShape/Branched', [...$this->props()]);
        }

        return Inertia::render('DocShape/Branched', ['owner' => null]);
    }

    /**
     * The props helper's fill.
     *
     * @return array{owner: Person|User}
     */
    private function props(): array
    {
        return ['owner' => json_decode((string) request('owner'))];
    }
}
