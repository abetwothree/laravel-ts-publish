<?php

declare(strict_types=1);

namespace Workbench\App\Models\Sales\Report;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Workbench\App\Models\Kpi;

/**
 * Fixture: same basename AND same parent namespace segment as
 * Marketing\Report\Report — reproduces a same-basename alias collision one namespace level deep.
 */
class Report extends Model
{
    protected $table = 'sales_reports';

    /** @return MorphMany<Kpi, $this> */
    public function kpis(): MorphMany
    {
        return $this->morphMany(Kpi::class, 'reportable');
    }
}
