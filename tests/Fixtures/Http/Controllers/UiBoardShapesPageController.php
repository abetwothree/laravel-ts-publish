<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Packages\Ui\Board;

class UiBoardShapesPageController
{
    /**
     * Props holding a value object that keeps its members' overrides, by a docblock shape and an untyped helper.
     */
    public function show(): Response
    {
        return Inertia::render('Ui/BoardShapes', [
            'shaped' => $this->shaped(),
            'untyped' => $this->untyped(),
        ]);
    }

    /**
     * A board and its label, by a docblock shape.
     *
     * @return array{board: Board, label: string}
     */
    protected function shaped(): array
    {
        return ['board' => new Board, 'label' => 'Board'];
    }

    /**
     * A board and its label, with no declared return type.
     */
    protected function untyped()
    {
        return ['board' => new Board, 'label' => 'Board'];
    }
}
