import type { Kpi } from '../..';

/**
 * Fixture: same basename AND same parent namespace segment as
 * Sales\Report\Report — reproduces a same-basename alias collision one namespace level deep.
 *
 * @see Workbench\App\Models\Marketing\Report\Report
 */
export interface Report
{
    // Columns
    id: number;
    name: string;
    created_at: string | null;
    updated_at: string | null;
    // Relations
    kpis: Kpi[];
    // Counts
    kpis_count: number;
    // Exists
    kpis_exists: boolean;
}
