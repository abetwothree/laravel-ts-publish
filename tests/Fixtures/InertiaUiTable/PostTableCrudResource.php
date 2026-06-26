<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InertiaUiTable;

class PostTableCrudResource
{
    /**
     * Table prop returned through a service layer, matching Sonr CMS's
     * MerchandiseResource::index() shape.
     *
     * @return array<string, mixed>
     */
    public function index(): array
    {
        return [
            'posts' => PostTable::make()->defaultSort('-id'),
        ];
    }

    /**
     * Sibling form data with no table, matching a CRUD resource's create().
     *
     * @return array<string, mixed>
     */
    public function create(): array
    {
        return ['mode' => 'create'];
    }
}
