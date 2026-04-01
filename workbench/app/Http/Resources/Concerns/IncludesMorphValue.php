<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Concerns;

trait IncludesMorphValue
{
    /**
     * @return array{morphValue: string}
     */
    protected function includeMorphValue(): array
    {
        $morphValue = '';

        if (method_exists($this->resource, 'getMorphClass')) {
            $morphValue = $this->resource->getMorphClass();
        } elseif (method_exists($this->resource, 'getTable')) {
            // Fallback to table name if getMorphClass is not available
            $morphValue = $this->resource->getTable();
        }

        return ['morphValue' => $morphValue];
    }
}
