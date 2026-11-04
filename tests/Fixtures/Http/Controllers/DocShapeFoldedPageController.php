<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Person;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

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

    /** Renders an override's fill and a model's fill in cousin props of one key. */
    public function cousin(): Response
    {
        return Inertia::render('DocShape/Cousin', ['o' => ['p' => [...$this->personAlone()], 'q' => [...$this->userAlone()]]]);
    }

    /** Renders fills whose override's name survives only in a literal. */
    public function literal(): Response
    {
        return Inertia::render('DocShape/Literal', [
            'lo' => [...$this->literalOverride()],
            'lc' => [...$this->literalCrm()],
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

    /**
     * The override beside the model, and the literal `'User'`, each or null.
     *
     * @return array{k: Person|User|null, kind: 'User'|null}
     */
    private function literalOverride(): array
    {
        return ['k' => json_decode((string) request('k')), 'kind' => json_decode((string) request('kind'))];
    }

    /**
     * The override beside the model, the literal `'User'` and the CRM user, each or null.
     *
     * @return array{k: Person|User|null, kind: 'User'|null, c: CrmUser|null}
     */
    private function literalCrm(): array
    {
        return [
            'k' => json_decode((string) request('k')),
            'kind' => json_decode((string) request('kind')),
            'c' => json_decode((string) request('c')),
        ];
    }
}
