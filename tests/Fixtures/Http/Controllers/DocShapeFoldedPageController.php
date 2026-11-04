<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Person;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\User;

/** A controller whose page props fold props helpers' fills naming an override (`Person` as `User`) and the model. */
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

    /** Renders a fill holding the override and the model in sibling members, and the two from two fills. */
    public function split(): Response
    {
        return Inertia::render('DocShape/Split', [
            'split' => [...$this->splitPair()],
            'two' => [...$this->personAlone(), ...$this->userAlone()],
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

    /**
     * The override and the model in sibling members, each or null.
     *
     * @return array{n: Person|null, m: User|null}
     */
    private function splitPair(): array
    {
        return ['n' => json_decode((string) request('n')), 'm' => json_decode((string) request('m'))];
    }

    /**
     * The override alone, or null.
     *
     * @return array{n: Person|null}
     */
    private function personAlone(): array
    {
        return ['n' => json_decode((string) request('n'))];
    }

    /**
     * The model alone, or null.
     *
     * @return array{m: User|null}
     */
    private function userAlone(): array
    {
        return ['m' => json_decode((string) request('m'))];
    }
}
