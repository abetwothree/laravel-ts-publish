<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Workbench\App\ValueObjects\EnumWraps\EnumWrapRow;

/**
 * Rows holding the app's and the CRM's `Status`, sent through array_filter() and a keyed collection's all(), each of
 * which json_encode() may write as an object (GH-76).
 */
final class FilteredSameNamedRows
{
    /** @var list<EnumWrapRow> */
    public array $rows = [];

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'filtered' => array_filter(array_map(
                fn (EnumWrapRow $row) => $row->name === '' ? null : ['app' => $row->status, 'crm' => $row->crmStatus],
                $this->rows,
            )),
            'keyed' => collect($this->rows)->keyBy('name')->map(fn (EnumWrapRow $row) => ['app' => $row->status, 'crm' => $row->crmStatus])->all(),
        ];
    }
}
