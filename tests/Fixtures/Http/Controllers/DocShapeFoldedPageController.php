<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Person;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\User;

/** A controller whose page props fold a props helper's override-beside-model fill into nested literals. */
class DocShapeFoldedPageController
{
    /** Renders the fill folded under a prop and a nested prop, beside a fill naming the model alone. */
    public function folded(): Response
    {
        return Inertia::render('DocShape/Folded', [
            'prop' => [...$this->documented()],
            'nested' => ['y' => [...$this->documented()]],
            'model_only' => [...$this->modelOnly()],
        ]);
    }

    /**
     * The override beside the model.
     *
     * @return array{k: Person|User}
     */
    private function documented(): array
    {
        return ['k' => json_decode((string) request('k'))];
    }

    /**
     * The model alone, or null.
     *
     * @return array{k: User|null}
     */
    private function modelOnly(): array
    {
        return ['k' => json_decode((string) request('k'))];
    }
}
