<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InertiaUiTable;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Fixture demonstrating a controller where one action renders an inline table
 * and a sibling action renders a table-free form in the same file.
 *
 * Used to verify that taint detection flags the sibling `form()` route because
 * the controller file itself contains an inline `PostTable` reference.
 */
class InertiaInlineTableController
{
    /**
     * Renders an inline Inertia UI Table — this method taints the whole file.
     */
    public function index(): Response
    {
        return Inertia::render('Tables/Index', [
            'posts' => PostTable::make()->defaultSort('-id'),
        ]);
    }

    /**
     * Table-free form action that shares a file with the table-bearing index().
     */
    public function form(): Response
    {
        return Inertia::render('Tables/Form', ['mode' => 'create']);
    }
}
